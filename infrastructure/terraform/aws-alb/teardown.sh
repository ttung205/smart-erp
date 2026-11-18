#!/usr/bin/env bash
# Xóa hai ALB, 18 target group để không tốn tiền. Chạy trong AWS CloudShell (quyền quản trị). Dựng lại: xem README.md.
# Chỉ dùng khi đã tắt hết EC2/ASG; script không đụng tới EC2, ASG, RDS, EFS, S3.
set -euo pipefail
R="--region ap-southeast-1"
ASG=erp-app-asg

echo "1. Gỡ ASG khỏi các target group (ASG giữ nguyên, chỉ bỏ liên kết)"
TGS=$(aws autoscaling describe-auto-scaling-groups $R --auto-scaling-group-names "$ASG" --query 'AutoScalingGroups[0].TargetGroupARNs' --output text)
if [ -n "$TGS" ] && [ "$TGS" != "None" ]; then
  # AWS chỉ cho gỡ tối đa 10 target group mỗi lần
  echo $TGS | tr ' ' '\n' | xargs -n 10 aws autoscaling detach-load-balancer-target-groups $R --auto-scaling-group-name "$ASG" --target-group-arns
  sleep 20
fi

echo "2. Xóa hai ALB (listener và rule bị xóa theo)"
for arn in $(aws elbv2 describe-load-balancers $R --query 'LoadBalancers[?starts_with(LoadBalancerName,`erp-`)].LoadBalancerArn' --output text); do
  aws elbv2 delete-load-balancer $R --load-balancer-arn "$arn"
done
aws elbv2 wait load-balancers-deleted $R --names erp-alb erp-internal 2>/dev/null || sleep 60

echo "3. Xóa target group"
for arn in $(aws elbv2 describe-target-groups $R --query 'TargetGroups[?starts_with(TargetGroupName,`tg-erp-`)||starts_with(TargetGroupName,`tg-int-`)].TargetGroupArn' --output text); do
  aws elbv2 delete-target-group $R --target-group-arn "$arn"
done

echo "Còn lại:"
aws elbv2 describe-load-balancers $R --query 'LoadBalancers[].LoadBalancerName' --output text
aws elbv2 describe-target-groups $R --query 'TargetGroups[].TargetGroupName' --output text
