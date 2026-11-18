# Dựng lại lớp cân bằng tải của Smart ERP trên AWS (hai ALB, 18 target group, listener, rule) đúng như đã chạy.
# Mục đích: xóa ALB khi không dùng để không tốn tiền, cần thì `terraform apply` để có lại trong vài phút.
#
# KHÔNG nằm trong Terraform (giữ nguyên trên AWS, không tốn tiền): VPC, subnet, security group, ASG, launch template,
# EC2, RDS, EFS, S3, SSM, IAM, chứng chỉ ACM. File này chỉ tham chiếu chúng qua biến.
# Sau khi apply, trỏ lại CNAME erp.transportbooking.xyz sang output alb_dns_name (DNS ở pavietnam.vn, không quản lý ở đây).
#
# Cách dùng: xem README.md cùng thư mục.

terraform {
  required_version = ">= 1.6"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.region
}

variable "region" {
  type    = string
  default = "ap-southeast-1"
}

variable "vpc_id" {
  type    = string
  default = "vpc-0d5572659bc21e663"
}

variable "subnet_ids" {
  description = "Hai subnet ở hai AZ cho cả hai ALB"
  type        = list(string)
  default     = ["subnet-0daded90e7fd87170", "subnet-0dc2597644133e822"]
}

variable "public_alb_sg_id" {
  type    = string
  default = "sg-0f50768e72274a9db"
}

variable "internal_alb_sg_id" {
  type    = string
  default = "sg-03efa6b89ff858711"
}

variable "certificate_arn" {
  type    = string
  default = "arn:aws:acm:ap-southeast-1:002530139989:certificate/c0d5bfff-2663-4a33-89a1-8f414207be58"
}

variable "asg_name" {
  type    = string
  default = "erp-app-asg"
}

variable "machine_a_instance_id" {
  description = "Máy A (RabbitMQ, Mongo, chat, notification, Grafana): đăng ký tay vào mọi target group"
  type        = string
  default     = "i-0f3373bf8da54d26e"
}

locals {
  # Target group công khai (cổng 80, qua Kong/nginx). interval/healthy là thông số đã chạy thật.
  public_tgs = {
    web       = { path = "/health", interval = 30, healthy = 5, asg = true }
    auth      = { path = "/api/v1/auth/health", interval = 30, healthy = 2, asg = true }
    catalog   = { path = "/api/v1/catalog/health", interval = 10, healthy = 2, asg = true }
    purchase  = { path = "/api/v1/purchase/health", interval = 10, healthy = 2, asg = true }
    inventory = { path = "/api/v1/inventory/health", interval = 10, healthy = 2, asg = true }
    sales     = { path = "/api/v1/sales/health/live", interval = 10, healthy = 2, asg = true }
    eoffice   = { path = "/api/v1/eoffice/health", interval = 10, healthy = 2, asg = true }
    finance   = { path = "/api/v1/finance/health", interval = 10, healthy = 2, asg = true }
    realtime  = { path = "/health", interval = 30, healthy = 5, asg = false } # chat + notification: chỉ máy A
    grafana   = { path = "/grafana/api/health", interval = 30, healthy = 5, asg = false }
  }

  # Target group nội bộ: mỗi cổng service một TG. notification chỉ ở máy A.
  internal_tgs = {
    identity     = { port = 8001, path = "/api/health", asg = true }
    catalog      = { port = 8002, path = "/api/health", asg = true }
    purchase     = { port = 8003, path = "/api/v1/purchase/health", asg = true }
    inventory    = { port = 8004, path = "/api/v1/inventory/health", asg = true }
    sales        = { port = 8005, path = "/api/health/live", asg = true }
    notification = { port = 8006, path = "/health", asg = false }
    eoffice      = { port = 8007, path = "/api/health", asg = true }
    finance      = { port = 8008, path = "/api/health", asg = true }
  }

  # Rule của listener 443 (priority -> path -> tg). Mặc định của listener là tg-erp-web.
  public_rules = {
    10 = { paths = ["/api/v1/chat*", "/api/v1/notifications*"], tg = "realtime" }
    15 = { paths = ["/api/v1/inventory*"], tg = "inventory" }
    20 = { paths = ["/grafana*"], tg = "grafana" }
    31 = { paths = ["/api/v1/catalog*"], tg = "catalog" }
    32 = { paths = ["/api/v1/purchase*"], tg = "purchase" }
    33 = { paths = ["/api/v1/sales*"], tg = "sales" }
    34 = { paths = ["/api/v1/eoffice*"], tg = "eoffice" }
    35 = { paths = ["/api/v1/finance*"], tg = "finance" }
    36 = { paths = ["/api/v1/auth*"], tg = "auth" }
  }
}

# ---------------------------------------------------------------- Target group
resource "aws_lb_target_group" "public" {
  for_each = local.public_tgs

  name                 = "tg-erp-${each.key}"
  port                 = 80
  protocol             = "HTTP"
  target_type          = "instance"
  vpc_id               = var.vpc_id
  deregistration_delay = 300

  health_check {
    path                = each.value.path
    matcher             = "200"
    interval            = each.value.interval
    timeout             = 5
    healthy_threshold   = each.value.healthy
    unhealthy_threshold = 2
  }
}

resource "aws_lb_target_group" "internal" {
  for_each = local.internal_tgs

  name                 = "tg-int-${each.key}"
  port                 = each.value.port
  protocol             = "HTTP"
  target_type          = "instance"
  vpc_id               = var.vpc_id
  deregistration_delay = 300

  health_check {
    path                = each.value.path
    matcher             = "200"
    interval            = 10
    timeout             = 5
    healthy_threshold   = 2
    unhealthy_threshold = 2
  }
}

# ---------------------------------------------------------------- Load balancer
resource "aws_lb" "public" {
  name               = "erp-alb"
  load_balancer_type = "application"
  internal           = false
  security_groups    = [var.public_alb_sg_id]
  subnets            = var.subnet_ids
  idle_timeout       = 60
}

resource "aws_lb" "internal" {
  name               = "erp-internal"
  load_balancer_type = "application"
  internal           = true
  security_groups    = [var.internal_alb_sg_id]
  subnets            = var.subnet_ids
  idle_timeout       = 60
}

# ---------------------------------------------------------------- Listener và rule (ALB công khai)
resource "aws_lb_listener" "http_redirect" {
  load_balancer_arn = aws_lb.public.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"
    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.public.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-Res-PQ-2025-09"
  certificate_arn   = var.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.public["web"].arn
  }
}

resource "aws_lb_listener_rule" "public" {
  for_each = local.public_rules

  listener_arn = aws_lb_listener.https.arn
  priority     = each.key

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.public[each.value.tg].arn
  }

  condition {
    path_pattern {
      values = each.value.paths
    }
  }
}

# ---------------------------------------------------------------- Listener (ALB nội bộ): mỗi cổng một TG
resource "aws_lb_listener" "internal" {
  for_each = local.internal_tgs

  load_balancer_arn = aws_lb.internal.arn
  port              = each.value.port
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.internal[each.key].arn
  }
}

# ---------------------------------------------------------------- Gắn máy vào target group
# Máy trong ASG: ASG tự đăng ký/hủy đăng ký máy theo thẻ target group.
# (Cần aws_autoscaling_attachment cho từng TG; ASG và launch template không do file này quản lý.)
resource "aws_autoscaling_attachment" "public" {
  for_each = { for k, v in local.public_tgs : k => v if v.asg }

  autoscaling_group_name = var.asg_name
  lb_target_group_arn    = aws_lb_target_group.public[each.key].arn
}

resource "aws_autoscaling_attachment" "internal" {
  for_each = { for k, v in local.internal_tgs : k => v if v.asg }

  autoscaling_group_name = var.asg_name
  lb_target_group_arn    = aws_lb_target_group.internal[each.key].arn
}

# Máy A không thuộc ASG: đăng ký tay vào cả 18 target group.
resource "aws_lb_target_group_attachment" "machine_a_public" {
  for_each = local.public_tgs

  target_group_arn = aws_lb_target_group.public[each.key].arn
  target_id        = var.machine_a_instance_id
}

resource "aws_lb_target_group_attachment" "machine_a_internal" {
  for_each = local.internal_tgs

  target_group_arn = aws_lb_target_group.internal[each.key].arn
  target_id        = var.machine_a_instance_id
}

# ---------------------------------------------------------------- Đầu ra
output "alb_dns_name" {
  description = "Trỏ CNAME erp.transportbooking.xyz về giá trị này (ở pavietnam.vn)"
  value       = aws_lb.public.dns_name
}

output "internal_alb_dns_name" {
  description = "Đặt INTERNAL_LB_HOST=<giá trị này> trong .env của MỌI máy (và trong SSM /smart-erp/prod/env-app nếu có)"
  value       = aws_lb.internal.dns_name
}
