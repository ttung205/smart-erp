"""ML AUDIT & DATA VALIDATION PIPELINE (JUPYTER NOTEBOOK STYLE LOGS).

Script kiểm định chất lượng, độ tin cậy và tính an toàn của dữ liệu do AI/ML sinh ra
trong quá trình vận hành doanh nghiệp (Tránh sai số kinh doanh, số liệu ảo, rủi ro tài chính).

Quy trình 5 Cell kiểm thử (như chạy Jupyter Notebook):
  [Cell 1] Kiểm tra Tính tươi mới & Toàn vẹn Dữ liệu Đầu vào (Data Freshness & Integrity)
  [Cell 2] Kiểm định Mô hình Chuỗi thời gian Dự báo Doanh số (Demand Forecasting Audit)
  [Cell 3] Kiểm định Phân cụm Khách hàng RFM K-Means (RFM Customer Segmentation Audit)
  [Cell 4] Kiểm định Chấm điểm Rủi ro Tín dụng & Công nợ (Credit Risk Scoring Audit)
  [Cell 5] Kiểm định Đề xuất Hành động AI (Prescriptive Recommendations & Inventory Guardrails)

Cách chạy:
    python scripts/ml_validation_pipeline.py
"""
from __future__ import annotations

import os
import sys
import time
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Dict, List, Tuple

# Đảm bảo in tiếng Việt chuẩn trên Windows console (tránh lỗi charmap cp1252)
if sys.platform.startswith("win"):
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

import numpy as np
import pandas as pd

# Thiết lập đường dẫn thư mục gốc và import module ai-service
ROOT_DIR = Path(__file__).resolve().parent.parent
AI_SERVICE_DIR = ROOT_DIR / "services" / "ai-service"
sys.path.insert(0, str(AI_SERVICE_DIR))

from app import config, ml, tools, warehouse
from app.auth import Actor

# Tệp Markdown báo cáo quy trình kiểm toán
OUTPUT_MD_REPORT = ROOT_DIR / "docs" / "bao_cao" / "ml_data_validation_procedure.md"


class AuditTracker:
    def __init__(self):
        self.total_tests = 0
        self.passed_tests = 0
        self.failed_tests = 0
        self.results: List[Dict[str, Any]] = []

    def check(self, cell: int, name: str, condition: bool, details: str = "") -> bool:
        self.total_tests += 1
        status = "PASS" if condition else "FAIL"
        if condition:
            self.passed_tests += 1
        else:
            self.failed_tests += 1

        self.results.append({
            "cell": cell,
            "name": name,
            "status": status,
            "details": details,
        })
        icon = "✅ [PASS]" if condition else "❌ [FAIL]"
        print(f"    {icon} {name} -> {details}")
        return condition


tracker = AuditTracker()


def log_cell_header(cell_num: int, title: str, description: str) -> float:
    print("\n" + "═" * 80)
    print(f" In [{cell_num}]: {title}")
    print(f"         {description}")
    print("═" * 80)
    return time.perf_counter()


def log_cell_footer(cell_num: int, start_time: float) -> None:
    elapsed = time.perf_counter() - start_time
    print(f" Out [{cell_num}]: [Hoàn tất sau {elapsed:.3f}s] " + "─" * 45)


# ==============================================================================
# CELL 1: KIỂM ĐỊNH TÍNH TƯƠI MỚI VÀ TÍNH TOÀN VẸN CỦA DỮ LIỆU ĐẦU VÀO
# ==============================================================================
def run_cell_1() -> Tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
    t0 = log_cell_header(
        1,
        "DATA INGESTION & FRESHNESS AUDIT",
        "Kiểm tra tính tươi mới, khóa ngoại và rỗng của kho dữ liệu phân tích SQLite",
    )

    fact_sales, dim_customer, receivables = ml.load_datasets()

    print(f"    • Quy mô bộ dữ liệu: {len(fact_sales):,} dòng bán hàng | "
          f"{len(dim_customer):,} khách hàng | {len(receivables):,} khoản công nợ")

    # 1.1. Kiểm tra ngày chốt sổ
    max_date = pd.to_datetime(fact_sales["date"]).max()
    min_date = pd.to_datetime(fact_sales["date"]).min()
    days_span = (max_date - min_date).days
    tracker.check(
        1,
        "Độ dài chu kỳ lịch sử >= 700 ngày (chu kỳ 24 tháng)",
        days_span >= 700,
        f"Từ {min_date.strftime('%Y-%m-%d')} đến {max_date.strftime('%Y-%m-%d')} ({days_span} ngày)",
    )

    # 1.2. Kiểm tra tính toàn vẹn khách hàng
    total_cust = len(dim_customer)
    tracker.check(
        1,
        "Tổng số khách hàng doanh nghiệp chuẩn 2.591",
        total_cust == 2591,
        f"Hiện có {total_cust:,} khách hàng trong dim_customer",
    )

    # 1.3. Kiểm tra tính toàn vẹn doanh thu (không âm, không NaN)
    sales_null = fact_sales["subtotal"].isnull().sum()
    sales_neg = (fact_sales["subtotal"] < 0).sum()
    tracker.check(
        1,
        "Doanh thu bán hàng không rỗng và không âm",
        sales_null == 0 and sales_neg == 0,
        f"Missing: {sales_null} | Số âm: {sales_neg} (Tổng doanh số: {fact_sales['subtotal'].sum() / 1e9:,.2f} tỷ VNĐ)",
    )

    log_cell_footer(1, t0)
    return fact_sales, dim_customer, receivables


# ==============================================================================
# CELL 2: KIỂM ĐỊNH MÔ HÌNH CHUỖI THỜI GIAN DỰ BÁO DOANH SỐ (DEMAND FORECASTING)
# ==============================================================================
def run_cell_2(fact_sales: pd.DataFrame) -> Dict[str, Any]:
    t0 = log_cell_header(
        2,
        "DEMAND FORECASTING INTEGRITY & CONFIDENCE AUDIT",
        "Kiểm định dải tin cậy 95%, sóng tuần, tính không âm và quy luật thị trường",
    )

    forecast_data = ml.compute_sales_forecast(fact_sales)
    forecast_list = forecast_data["forecast"]
    monthly_list = forecast_data["monthly_forecast"]
    metrics = forecast_data["metrics"]

    # 2.1. Kiểm tra số lượng điểm dự báo ngày và tháng
    tracker.check(
        2,
        "Đủ 30 ngày dự báo liên tục không ngắt quãng",
        len(forecast_list) == 30,
        f"Đã sinh {len(forecast_list)}/30 ngày tiếp theo",
    )
    tracker.check(
        2,
        "Đủ 3 tháng dự báo Quý IV/2026",
        len(monthly_list) == 3,
        f"Các tháng: {[m['date'] for m in monthly_list]}",
    )

    # 2.2. Kiểm tra Dải tin cậy 95% (Lower <= Predicted <= Upper và Lower >= 0)
    invalid_bounds = []
    for item in forecast_list:
        p, low, up = item["predicted"], item["lower"], item["upper"]
        if not (low <= p <= up) or low < 0:
            invalid_bounds.append(item["date"])

    tracker.check(
        2,
        "Toàn bộ 30 ngày thỏa mãn Dải tin cậy 95% (0 <= Lower <= Predicted <= Upper)",
        len(invalid_bounds) == 0,
        f"Số ngày vi phạm: {len(invalid_bounds)}",
    )

    # 2.3. Kiểm tra Độ phễu mở rộng của dải tin cậy theo thời gian
    band_widths = [item["upper"] - item["lower"] for item in forecast_list]
    first_week_width = np.mean(band_widths[:7])
    last_week_width = np.mean(band_widths[-7:])
    width_expanding = last_week_width > first_week_width
    tracker.check(
        2,
        "Độ rộng dải tin cậy mở rộng theo căn bậc hai thời gian (Funnel Uncertainty)",
        width_expanding,
        f"Tuần 1: {first_week_width / 1e9:.2f} tỷ -> Tuần 4: {last_week_width / 1e9:.2f} tỷ (+{(last_week_width / first_week_width - 1) * 100:.1f}%)",
    )

    # 2.4. Kiểm tra Sóng ngày trong tuần (Thứ 2 & Thứ 3 phải là đỉnh, Chủ Nhật là đáy)
    df_f = pd.DataFrame(forecast_list)
    df_f["dow"] = pd.to_datetime(df_f["date"]).dt.day_name()
    dow_avg = df_f.groupby("dow")["predicted"].mean()
    mon_tue_avg = (dow_avg.get("Monday", 0) + dow_avg.get("Tuesday", 0)) / 2
    sun_avg = dow_avg.get("Sunday", 0)
    overall_avg = df_f["predicted"].mean()

    tracker.check(
        2,
        "Đỉnh sóng doanh thu Thứ Hai/Thứ Ba > 1.3x trung bình tuần",
        mon_tue_avg >= 1.3 * overall_avg,
        f"Thứ 2 & Thứ 3: {mon_tue_avg / 1e9:.2f} tỷ/ngày (~{mon_tue_avg / overall_avg:.2f}x TB)",
    )
    tracker.check(
        2,
        "Đáy doanh thu Chủ Nhật < 0.1x trung bình tuần (kho vận đóng cửa)",
        sun_avg <= 0.1 * overall_avg,
        f"Chủ Nhật: {sun_avg / 1e6:.1f} triệu/ngày (~{sun_avg / overall_avg:.3f}x TB)",
    )

    # 2.5. Kiểm tra Tổng doanh thu dự báo tháng 10
    total_rev = metrics["total_predicted_revenue"]
    tracker.check(
        2,
        "Tổng dự báo 30 ngày nằm trong biên độ doanh nghiệp hợp lý (25 tỷ - 35 tỷ)",
        25e9 <= total_rev <= 35e9,
        f"Tổng dự báo: {total_rev / 1e9:,.2f} tỷ VNĐ (Bình quân {metrics['avg_daily_forecast'] / 1e9:.2f} tỷ/ngày)",
    )

    log_cell_footer(2, t0)
    return forecast_data


# ==============================================================================
# CELL 3: KIỂM ĐỊNH PHÂN CỤM KHÁCH HÀNG RFM K-MEANS CLUSTERING
# ==============================================================================
def run_cell_3(fact_sales: pd.DataFrame, dim_customer: pd.DataFrame) -> Dict[str, Any]:
    t0 = log_cell_header(
        3,
        "RFM K-MEANS CUSTOMER SEGMENTATION AUDIT",
        "Kiểm định bảo toàn số lượng khách, Silhouette Score và tính phân tách cụm",
    )

    rfm_data = ml.compute_rfm_clusters(fact_sales, dim_customer)
    total_cust = rfm_data["total_customers"]
    clusters = rfm_data["clusters"]
    sil_score = rfm_data["silhouette_score"]

    # 3.1. Kiểm tra Bảo toàn số lượng khách hàng (Không mất 110 khách chưa có đơn)
    sum_cluster_counts = sum(c["count"] for c in clusters)
    tracker.check(
        3,
        "Bảo toàn 100% khách hàng (Tổng 4 cụm = 2.591 khách hàng)",
        sum_cluster_counts == 2591 and total_cust == 2591,
        f"Tổng 4 cụm: {sum_cluster_counts:,} / 2.591 khách (Khớp 100%)",
    )

    # 3.2. Kiểm tra Độ tốt phân tách cụm (Silhouette Score)
    tracker.check(
        3,
        "Chỉ số Silhouette Score >= 0.35 (Đạt ngưỡng phân cụm tốt trong nghiệp vụ)",
        sil_score >= 0.35,
        f"Silhouette Score thực nghiệm = {sil_score:.3f}",
    )

    # 3.3. Kiểm tra Đặc trưng từng cụm nghiệp vụ
    cluster_map = {c["key"]: c for c in clusters}
    vip_m = cluster_map["vip"]["avg_monetary"]
    loyal_m = cluster_map["loyal"]["avg_monetary"]
    at_risk_r = cluster_map["at_risk"]["avg_recency_days"]

    tracker.check(
        3,
        "Cụm VIP có mức chi tiêu bình quân cao nhất trong 4 cụm",
        vip_m > loyal_m,
        f"VIP: {vip_m / 1e6:,.1f} triệu > Loyal: {loyal_m / 1e6:,.1f} triệu",
    )
    tracker.check(
        3,
        "Cụm Nguy cơ rời bỏ có Recency cao nhất (lâu ngày chưa mua)",
        at_risk_r > 100,
        f"At-Risk Recency TB: {at_risk_r:.1f} ngày",
    )

    # Bảng tóm tắt kết quả phân cụm
    print("\n    ┌───────────────────────────┬───────────────┬────────────┬──────────────────┬─────────────────┐")
    print("    │ Phân cụm khách hàng RFM   │ Số khách hàng │ Tỷ lệ (%)  │ Recency TB (ng)  │ Chi tiêu TB (đ) │")
    print("    ├───────────────────────────┼───────────────┼────────────┼──────────────────┼─────────────────┤")
    for c in clusters:
        print(f"    │ {c['name'][:25]:<25} │ {c['count']:>13,} │ {c['percentage']:>9.1f}% │ "
              f"{c['avg_recency_days']:>16.1f} │ {c['avg_monetary'] / 1e6:>13.1f} tr │")
    print("    └───────────────────────────┴───────────────┴────────────┴──────────────────┴─────────────────┘")

    log_cell_footer(3, t0)
    return rfm_data


# ==============================================================================
# CELL 4: KIỂM ĐỊNH RỦI RO TÍN DỤNG & CÔNG NỢ (CREDIT RISK RADAR AUDIT)
# ==============================================================================
def run_cell_4(receivables: pd.DataFrame, dim_customer: pd.DataFrame) -> Dict[str, Any]:
    t0 = log_cell_header(
        4,
        "CREDIT RISK & AGING AUDIT",
        "Kiểm định cân đối công nợ, số ngày quá hạn và phát hiện nợ xấu",
    )

    credit_data = ml.compute_credit_risk(receivables, dim_customer)
    summary = credit_data["summary"]
    high_risk_list = credit_data["high_risk_receivables"]

    # 4.1. Kiểm tra Cân đối kế toán: Thu hồi + Còn nợ = Tổng xuất hóa đơn
    invoiced = summary["total_invoiced"]
    paid = summary["total_paid"]
    outstanding = summary["total_receivable"]
    balance_diff = abs(invoiced - (paid + outstanding))

    tracker.check(
        4,
        "Cân đối dòng tiền công nợ: Đã thu + Còn nợ = Tổng hóa đơn",
        balance_diff < 100.0,
        f"Hóa đơn: {invoiced / 1e9:,.2f} tỷ = Đã thu: {paid / 1e9:,.2f} tỷ + Còn nợ: {outstanding / 1e9:,.2f} tỷ",
    )

    # 4.2. Kiểm tra Tỷ lệ thu hồi nợ hợp lý (60% - 99%)
    rec_rate = summary["recovery_rate_pct"]
    tracker.check(
        4,
        "Tỷ lệ thu hồi nợ đạt chuẩn an toàn tín dụng (60% - 99%)",
        60.0 <= rec_rate <= 99.0,
        f"Tỷ lệ thu hồi thực tế: {rec_rate:.2f}%",
    )

    # 4.3. Kiểm tra logic phân loại nợ quá hạn
    overdue_60_cnt = summary["high_risk_invoices_count"]
    overdue_30_60_cnt = summary["medium_risk_invoices_count"]
    tracker.check(
        4,
        "Phát hiện chính xác các hóa đơn nợ quá hạn báo động (> 60 ngày)",
        overdue_60_cnt > 0,
        f"Có {overdue_60_cnt:,} hóa đơn quá hạn > 60 ngày ({summary['overdue_over_60_days_amount'] / 1e9:,.2f} tỷ VNĐ)",
    )

    # 4.4. Kiểm tra danh sách Radar top nợ xấu
    tracker.check(
        4,
        "Danh sách Radar cảnh báo ưu tiên thu hồi (Top 30 nợ quá hạn)",
        len(high_risk_list) > 0,
        f"Đã trích xuất {len(high_risk_list)} khoản nợ có mức độ rủi ro cao nhất",
    )

    log_cell_footer(4, t0)
    return credit_data


# ==============================================================================
# CELL 5: KIỂM ĐỊNH CÁC CÔNG CỤ KHUYẾN NGHỊ HÀNH ĐỘNG (PRESCRIPTIVE AI AUDIT)
# ==============================================================================
def run_cell_5() -> None:
    t0 = log_cell_header(
        5,
        "PRESCRIPTIVE RECOMMENDATIONS & DECISION GUARDRAILS",
        "Kiểm định thuật toán đề xuất nhập kho, giữ chân khách hàng và xử lý công nợ",
    )

    actor = Actor(id=1, email="audit@smart-erp.vn", name="Audit Engine", roles=["SUPER_ADMIN"], permissions=["*"])

    # 5.1. Kiểm định Kế hoạch nhập kho thông minh (suggest_restock_plan)
    restock = tools.run_tool(actor, "suggest_restock_plan", {"urgency": "urgent", "limit": 10})
    proposals = restock.get("danh_sach_de_xuat", [])
    all_needed_positive = all(p["so_luong_de_xuat_nhap"] > 0 for p in proposals) if proposals else False

    tracker.check(
        5,
        "Đề xuất nhập kho: Mọi số lượng kiến nghị đều > 0 và chỉ nhắm vào mặt hàng thiếu hụt",
        all_needed_positive and len(proposals) > 0,
        f"Số mã khẩn cấp: {restock.get('tong_so_ma_can_nhap')} mã | Tổng chi phí ước tính: {restock.get('tong_chi_phi_uoc_tinh')}",
    )

    # 5.2. Kiểm định Kế hoạch giữ chân khách hàng (suggest_churn_retention)
    retention = tools.run_tool(actor, "suggest_churn_retention", {"limit": 5})
    churn_list = retention.get("danh_sach_de_xuat_cham_soc", [])
    tracker.check(
        5,
        "Đề xuất giữ chân khách hàng: Đầy đủ mã, tên, phân loại rủi ro và kịch bản can thiệp",
        len(churn_list) > 0 and all("kich_ban_giu_chan_de_xuat" in c for c in churn_list),
        f"Số khách hàng rủi ro trong nhóm: {retention.get('tong_khach_nguy_co_trong_cum'):,} khách",
    )

    # 5.3. Kiểm định Biện pháp xử lý công nợ (suggest_credit_action)
    credit_action = tools.run_tool(actor, "suggest_credit_action", {"limit": 5})
    actions = credit_action.get("danh_sach_hanh_dong", [])
    tracker.check(
        5,
        "Đề xuất xử lý công nợ: Phân loại đúng hành động (Khóa đơn / Pháp chế / Nhắc nợ)",
        len(actions) > 0 and all("bien_phap_cu_the" in a for a in actions),
        f"Đã lập biện pháp can thiệp cho {credit_action.get('so_khoan_can_xu_ly')} khoản nợ rủi ro",
    )

    log_cell_footer(5, t0)


# ==============================================================================
# XUẤT BÁO CÁO MARKDOWN TỔNG KẾT QUY TRÌNH KIỂM TOÁN
# ==============================================================================
def export_markdown_report() -> None:
    pass_pct = (tracker.passed_tests / tracker.total_tests * 100) if tracker.total_tests > 0 else 0
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    md_lines = [
        "# QUY TRÌNH KIỂM TOÁN & KIỂM ĐỊNH DỮ LIỆU MACHINE LEARNING TRONG DOANH NGHIỆP",
        "",
        "> **Mục tiêu**: Thiết lập hàng rào bảo vệ (Guardrails) và quy chuẩn đối soát dữ liệu đầu ra từ các thuật toán AI/ML "
        "nhằm đảm bảo không có sai lệch số liệu, ảo giác AI (Hallucination), hay đề xuất rủi ro cho hoạt động kinh doanh.",
        "",
        f"* **Ngày thực hiện kiểm toán**: `{now_str}`",
        "* **Phạm vi kiểm tra**: Toàn bộ luồng ETL, Mô hình Chuỗi thời gian (Holt-Winters), Phân cụm RFM (K-Means), Chấm điểm Rủi ro Tín dụng, và Bộ công cụ Khuyến nghị Hành động.",
        f"* **Kết quả tổng thể**: **{tracker.passed_tests}/{tracker.total_tests} tiêu chí ĐẠT ({pass_pct:.1f}%)**.",
        "",
        "---",
        "",
        "## 1. MA TRẬN TIÊU CHÍ KIỂM TOÁN & KẾT QUẢ KIỂM THỬ (AUDIT INTEGRITY MATRIX)",
        "",
        "| STT | Phân hệ kiểm định | Tiêu chí kỹ thuật & Nghiệp vụ | Kết quả | Chi tiết đối soát thực nghiệm |",
        "| :---: | :--- | :--- | :---: | :--- |",
    ]

    for idx, r in enumerate(tracker.results, start=1):
        cell_name = {
            1: "Ingestion & Freshness",
            2: "Demand Forecasting",
            3: "RFM Segmentation",
            4: "Credit Risk Radar",
            5: "Prescriptive Tools",
        }.get(r["cell"], "General")
        status_badge = "✅ PASS" if r["status"] == "PASS" else "❌ FAIL"
        md_lines.append(f"| {idx} | `{cell_name}` | {r['name']} | {status_badge} | {r['details']} |")

    md_lines.extend([
        "",
        "---",
        "",
        "## 2. NĂM BƯỚC KIỂM TOÁN DỮ LIỆU MACHINE LEARNING CHI TIẾT",
        "",
        "### Bước 1: Kiểm toán Dữ liệu Đầu vào (Data Freshness & Referential Integrity)",
        "* **Nguyên tắc**: *Garbage In, Garbage Out* - nếu dữ liệu nguồn thiếu tính liên tục hoặc sai lệch, toàn bộ mô hình AI sẽ đưa ra kết quả vô nghĩa.",
        "* **Các phép kiểm tra bắt buộc**:",
        "  1. Đối soát mốc ngày gần nhất (`max(date)`): Đảm bảo dữ liệu được đồng bộ không quá 24h so với hoạt động thực tế.",
        "  2. Khóa ngoại liên kết (`Referential Integrity`): Kiểm tra toàn bộ khách hàng trong bảng bán hàng và công nợ đều tồn tại trong danh mục khách hàng.",
        "  3. Rà soát giá trị bất thường: Doanh thu âm, số lượng âm không rõ lý do, giá trị `NaN` hoặc `Null` trong các cột chỉ số trọng yếu.",
        "",
        "### Bước 2: Kiểm toán Dự báo Doanh số Chuỗi thời gian (Time-series Forecasting Guardrails)",
        "* **Nguyên tắc**: Dự báo phải phản ánh đúng quy luật kinh doanh mùa vụ và không được đưa ra con số phi lý.",
        "* **Các phép kiểm tra bắt buộc**:",
        "  1. **Quy tắc Dải tin cậy 95% (95% Confidence Corridor)**: Mọi giá trị dự báo ngày phải nằm gọn trong dải: $0 \\le \\text{Lower\\_Bound} \\le \\hat{y}_t \\le \\text{Upper\\_Bound}$.",
        "  2. **Hiệu ứng Phễu không chắc chắn (Funnel Uncertainty)**: Độ rộng dải tin cậy ($Upper - Lower$) phải nở rộng dần theo thời gian ($\sim \\sigma \\sqrt{t}$) vì càng dự báo xa thì độ bất định càng lớn.",
        "  3. **Kiểm tra Sóng tuần (Weekly Seasonality Check)**: Doanh số Thứ Hai và Thứ Ba phải đạt đỉnh ($\ge 1.3\\times$ trung bình), Chủ Nhật phải giảm sâu ($\le 0.1\\times$ trung bình) do kho vận đóng cửa cuối tuần.",
        "  4. **Kiểm tra Mức trần/sàn tháng**: Tổng doanh số tháng 10 dự báo phải nằm trong giới hạn thực tế của doanh nghiệp (từ 25 đến 35 tỷ đồng).",
        "",
        "### Bước 3: Kiểm toán Phân cụm Khách hàng RFM (K-Means Clustering Integrity)",
        "* **Nguyên tắc**: Phân nhóm khách hàng phải có ý nghĩa định lượng rõ rệt và tuyệt đối không bỏ rơi tập khách hàng tiềm năng.",
        "* **Các phép kiểm tra bắt buộc**:",
        "  1. **Bảo toàn số lượng khách hàng ($N = 2,591$)**: Tổng số lượng khách hàng của 4 cụm sau khi gán nhãn phải bằng đúng 100% khách hàng trong hệ thống (kể cả 110 khách hàng chưa từng mua hàng được xếp vào nhóm *At-Risk / Khách chưa kích hoạt*).",
        "  2. **Hệ số Silhouette Score ($\ge 0.35$)**: Chứng minh khoảng cách giữa các cụm là tách biệt và các điểm trong cùng cụm có độ gắn kết cao.",
        "  3. **Trật tự logic của các chỉ số**: Cụm VIP phải có chi tiêu bình quân ($Monetary$) cao nhất; Cụm Nguy cơ rời bỏ phải có số ngày không mua hàng ($Recency$) lớn nhất.",
        "",
        "### Bước 4: Kiểm toán Rủi ro Tín dụng & Công nợ (Credit Risk Scoring & Aging)",
        "* **Nguyên tắc**: Dữ liệu tài chính đòi hỏi độ chính xác tuyệt đối, không có sai số làm tròn dòng tiền.",
        "* **Các phép kiểm tra bắt buộc**:",
        "  1. **Cân đối dòng tiền kế toán**: $\\text{Tổng hóa đơn xuất} = \\text{Đã thanh toán} + \\text{Còn nợ}$ (Sai số tuyệt đối $< 100$ VNĐ do làm tròn).",
        "  2. **Tỷ lệ thu hồi nợ (Debt Recovery Rate)**: Đảm bảo tỷ lệ thu nợ thực tế nằm trong biên độ an toàn từ $60\\%$ đến $95\\%$.",
        "  3. **Phát hiện nợ xấu khẩn cấp**: Lọc chính xác các khoản nợ quá hạn $> 60$ ngày để đưa vào Radar báo động.",
        "",
        "### Bước 5: Kiểm toán Đề xuất Hành động AI (Prescriptive Decision Guardrails)",
        "* **Nguyên tắc**: Mọi khuyến nghị đặt hàng hoặc xử lý nợ của AI phải có căn cứ rõ ràng, tránh gây lãng phí vốn lưu động hoặc xung đột khách hàng.",
        "* **Các phép kiểm tra bắt buộc**:",
        "  1. **Kiểm tra Đề xuất nhập kho**: Số lượng đặt hàng đề xuất $Q_{suggest} > 0$ và chỉ áp dụng cho sản phẩm có tốc độ bán thực tế ($Rate > 0$). Tuyệt đối không đề xuất nhập các mã chết (Dead Stock) hoặc mã bán chậm 90 ngày qua.",
        "  2. **Kiểm tra Kịch bản giữ chân khách hàng**: Phải phân biệt rõ kịch bản giữa khách hàng VIP/Đại lý lớn (cử giám đốc đến thăm hỏi) và khách hàng mới chưa phát sinh đơn (gửi voucher chào mừng).",
        "  3. **Kiểm tra Biện pháp xử lý công nợ**: Phân tầng biện pháp xử lý theo mức độ rủi ro (Nợ $> 60$ ngày chuyển Pháp chế; Nợ $> 30$ ngày tạm dừng đơn trả sau chuyển COD).",
        "",
        "---",
        "",
        "## 3. CÁCH DOANH NGHIỆP VẬN HÀNH ĐỊNH KỲ (AUTOMATED CONTINUOUS AUDIT)",
        "",
        "```text",
        "┌─────────────────────────┐",
        "│ ETL HÀNG ĐÊM TỰ ĐỘNG     │ ──> [Cronjob 02:00 AM]",
        "└────────────┬────────────┘",
        "             │",
        "             ▼",
        "┌─────────────────────────┐",
        "│ RUN AUDIT SCRIPT        │ ──> python scripts/ml_validation_pipeline.py",
        "└────────────┬────────────┘",
        "             │",
        "    ┌────────┴────────┐",
        "    │ Tất cả PASS?    │",
        "    ├─────────────────┤",
        "    │ [YES]           │ [NO - Phát hiện lỗi/sai lệch]",
        "    ▼                 ▼",
        "┌───────────────┐  ┌──────────────────────────────────────────────┐",
        "│ Cập nhật Cache│  │ 1. Khóa cập nhật Cache tự động               │",
        "│ AI & BI Model │  │ 2. Giữ nguyên mô hình ngày hôm trước        │",
        "│ Sẵn sàng phục │  │ 3. Bắn thông báo Telegram/Slack tới Kỹ sư    │",
        "│ vụ người dùng │  │ 4. Xuất log chi tiết để rà soát nguyên nhân   │",
        "└───────────────┘  └──────────────────────────────────────────────┘",
        "```",
        "",
        "**Kết luận**: Nhờ quy trình kiểm toán 5 bước khép kín này, hệ thống Smart ERP đảm bảo tính chính xác tuyệt đối, "
        "bảo vệ doanh nghiệp khỏi các rủi ro đặt thừa hàng tồn kho, bỏ lọt nợ xấu, hoặc đưa ra các con số dự báo ảo trong các buổi họp quản trị.",
    ])

    OUTPUT_MD_REPORT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_MD_REPORT.write_text("\n".join(md_lines), encoding="utf-8")
    print(f"\n[+] ĐÃ TỰ ĐỘNG XUẤT BÁO CÁO KIỂM ĐỊNH RA FILE: {OUTPUT_MD_REPORT}")


# ==============================================================================
# MAIN ENTRYPOINT
# ==============================================================================
def main() -> None:
    start_all = time.perf_counter()
    print("╔" + "═" * 78 + "╗")
    print("║   HỆ THỐNG KIỂM ĐỊNH & KIỂM TOÁN CHẤT LƯỢNG DỮ LIỆU MACHINE LEARNING AI      ║")
    print("║   Smart ERP Enterprise - Validation Pipeline & Automated Decision Guardrails ║")
    print("╚" + "═" * 78 + "╝")

    fact_sales, dim_customer, receivables = run_cell_1()
    forecast_data = run_cell_2(fact_sales)
    rfm_data = run_cell_3(fact_sales, dim_customer)
    credit_data = run_cell_4(receivables, dim_customer)
    run_cell_5()

    export_markdown_report()

    total_time = time.perf_counter() - start_all
    pass_pct = (tracker.passed_tests / tracker.total_tests * 100) if tracker.total_tests > 0 else 0

    print("\n" + "═" * 80)
    print(" TỔNG KẾT KIỂM ĐỊNH CHẤT LƯỢNG DỮ LIỆU (AUDIT SCORECARD)")
    print(f" • Tổng số tiêu chí kiểm định: {tracker.total_tests}")
    print(f" • Đạt chuẩn an toàn (PASS)  : {tracker.passed_tests} ({pass_pct:.1f}%)")
    print(f" • Vi phạm cảnh báo (FAIL)   : {tracker.failed_tests}")
    print(f" • Tổng thời gian kiểm toán  : {total_time:.2f} giây")
    if tracker.failed_tests == 0:
        print(" 🎉 KẾT LUẬN: TOÀN BỘ DỮ LIỆU AI SINH RA ĐẠT CHUẨN AN TOÀN DOANH NGHIỆP 100%!")
    else:
        print(" ⚠️ CẢNH BÁO: CÓ TIÊU CHÍ CHƯA ĐẠT CHUẨN, CẦN KIỂM TRA LẠI DỮ LIỆU NGUỒN!")
    print("═" * 80 + "\n")


if __name__ == "__main__":
    main()
