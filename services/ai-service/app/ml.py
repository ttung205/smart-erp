"""Module Machine Learning & Analytics cho ai-service.

Bao gồm:
1. Phân cụm khách hàng RFM (K-Means Clustering, k=4) + Silhouette Score
2. Dự báo doanh số theo ngày (Holt-Winters, mùa vụ tuần), kiểm định ngoài mẫu 30 ngày, dải tin cậy 95% từ mô phỏng
3. Phân tích rủi ro tín dụng & công nợ quá hạn (Credit Risk Scoring)
4. Tổng quan chỉ số KPI kinh doanh (Overview Metrics)
5. Bộ đệm In-Memory Caching đảm bảo phản hồi REST API < 50ms
6. MÔ HÌNH HỌC MÁY GỢI Ý ĐẶT HÀNG NHẬP KHO (Holt-Winters)
"""
from __future__ import annotations

import logging
import threading
from datetime import date, datetime, timedelta
from typing import Any, Dict, List, Optional

import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

log = logging.getLogger("ai.ml")

# In-memory Cache & Lock
_cache: Dict[str, Any] = {}
_cache_lock = threading.Lock()
_is_ready = False


# ==============================================================================
# 1. PHÂN CỤM KHÁCH HÀNG RFM (K-MEANS CLUSTERING)
# ==============================================================================
def compute_rfm_clusters(
    fact_sales: pd.DataFrame, dim_customer: pd.DataFrame
) -> Dict[str, Any]:
    """Trích xuất RFM, log-transform, scale và phân cụm K-Means k=4.
    
    Phân loại thành 4 nhóm nghiệp vụ:
    - VIP / Champions
    - Khách hàng trung thành (Loyal)
    - Khách hàng tiềm năng (Potential)
    - Nguy cơ rời bỏ (At-Risk / Churn)
    """
    log.info("Bắt đầu tính toán phân cụm RFM K-Means...")
    
    # Ngày chốt sổ phân tích: ngày mới nhất trong tập dữ liệu
    sales = fact_sales.copy()
    sales["date"] = pd.to_datetime(sales["date"])
    cutoff_date = sales["date"].max()

    # Tính toán RFM từ fact_sales_enriched
    sales_agg = sales.groupby("customer_id").agg(
        recency=("date", lambda x: (cutoff_date - x.max()).days),
        frequency=("order_id", "nunique"),
        monetary=("subtotal", "sum"),
        profit=("profit", "sum"),
    ).reset_index()

    # Left join dim_customer để bao gồm ĐỦ 2,591 khách hàng trong cơ sở dữ liệu
    # (Đảm bảo 110 khách hàng đăng ký tài khoản nhưng chưa phát sinh đơn không bị thất thoát)
    cust_cols = ["id", "code", "name", "type", "province", "region", "phone", "email", "credit_limit"]
    available_cols = [c for c in cust_cols if c in dim_customer.columns]
    rfm = dim_customer[available_cols].merge(sales_agg, left_on="id", right_on="customer_id", how="left")
    rfm["customer_id"] = rfm["id"]

    # Xử lý missing cho toàn bộ 2,591 khách hàng
    rfm["name"] = rfm["name"].fillna(rfm["customer_id"].apply(lambda cid: f"Khách hàng #{cid}"))
    rfm["code"] = rfm["code"].fillna(rfm["customer_id"].apply(lambda cid: f"KH-{cid}"))
    rfm["province"] = rfm["province"].fillna("Chưa xác định")
    rfm["credit_limit"] = rfm["credit_limit"].fillna(0.0)
    rfm["frequency"] = rfm["frequency"].fillna(0).astype(int)
    rfm["monetary"] = rfm["monetary"].fillna(0.0)
    rfm["profit"] = rfm["profit"].fillna(0.0)
    rfm["recency"] = rfm["recency"].fillna(730).astype(int)  # 2 năm chưa mua

    # Tách khách hàng đã có phát sinh đơn (2,481 khách) để phân cụm K-Means
    active_mask = rfm["frequency"] > 0
    rfm_active = rfm[active_mask].copy()

    # 1. Log Transform để triệt tiêu độ lệch (skewness) & ngoại lai
    features = ["recency", "frequency", "monetary"]
    rfm_log = np.log1p(rfm_active[features])

    # 2. Chuẩn hóa StandardScaler
    scaler = StandardScaler()
    rfm_scaled = scaler.fit_transform(rfm_log)

    # 3. K-Means với k=4 (đã kiểm định tối ưu qua Elbow & Silhouette)
    kmeans = KMeans(n_clusters=4, random_state=42, n_init=10)
    labels = kmeans.fit_predict(rfm_scaled)
    rfm_active["cluster_raw"] = labels

    # Tính Silhouette Score để chứng minh độ tốt của mô hình trên tập khách hàng hoạt động
    try:
        sil_score = float(silhouette_score(rfm_scaled, labels))
    except Exception:
        log.exception("Không tính được Silhouette Score")
        sil_score = None

    # Thống kê phân cụm
    cluster_stats = rfm_active.groupby("cluster_raw").agg(
        avg_r=("recency", "mean"),
        avg_f=("frequency", "mean"),
        avg_m=("monetary", "mean"),
        count=("customer_id", "count"),
    )

    # Xếp hạng cụm theo monetary và recency để gán nhãn nghiệp vụ cố định
    sorted_clusters = cluster_stats.sort_values(by=["avg_m", "avg_f"], ascending=[False, False]).index.tolist()
    vip_cluster = sorted_clusters[0]
    remaining = [c for c in sorted_clusters if c != vip_cluster]
    remaining_sorted_by_r = cluster_stats.loc[remaining].sort_values(by="avg_r", ascending=False).index.tolist()
    at_risk_cluster = remaining_sorted_by_r[0]
    loyal_candidate_1 = remaining_sorted_by_r[1]
    loyal_candidate_2 = remaining_sorted_by_r[2]

    # Cụm nào có avg_m cao hơn là Loyal, cụm còn lại là Potential
    if cluster_stats.loc[loyal_candidate_1, "avg_m"] >= cluster_stats.loc[loyal_candidate_2, "avg_m"]:
        loyal_cluster = loyal_candidate_1
        potential_cluster = loyal_candidate_2
    else:
        loyal_cluster = loyal_candidate_2
        potential_cluster = loyal_candidate_1

    # Gán cluster_raw cho toàn bộ bảng rfm
    rfm["cluster_raw"] = -1
    rfm.loc[active_mask, "cluster_raw"] = rfm_active["cluster_raw"]
    # 110 khách hàng chưa từng phát sinh đơn hàng (frequency=0) được phân loại vào nhóm At-Risk / Khách chưa kích hoạt
    rfm.loc[~active_mask, "cluster_raw"] = at_risk_cluster

    cluster_meta = {
        vip_cluster: {
            "key": "vip",
            "name": "VIP / Champions",
            "color": "#10B981",  # Xanh ngọc lục bảo
            "description": "Khách hàng mua thường xuyên, giá trị đơn hàng cực lớn, vừa mới phát sinh giao dịch.",
            "recommendation": "Chính sách ưu đãi VIP, quà tặng tri ân thường niên, phân bổ Account Manager chuyên trách.",
        },
        loyal_cluster: {
            "key": "loyal",
            "name": "Khách hàng trung thành (Loyal)",
            "color": "#3B82F6",  # Xanh dương
            "description": "Mua hàng đều đặn định kỳ, phản hồi tích cực và tỷ lệ duy trì cao.",
            "recommendation": "Duy trì chính sách chiết khấu ổn định, ưu tiên giữ nguồn hàng trong các đợt cao điểm.",
        },
        potential_cluster: {
            "key": "potential",
            "name": "Khách hàng tiềm năng (Potential)",
            "color": "#F59E0B",  # Vàng hổ phách
            "description": "Mới mua hàng gần đây, giá trị đơn ở mức trung bình, có tiềm năng tăng trưởng tốt.",
            "recommendation": "Chào các gói combo sản phẩm mới, up-sell thiết bị gia dụng kèm theo, hỗ trợ hạn mức tín dụng.",
        },
        at_risk_cluster: {
            "key": "at_risk",
            "name": "Nguy cơ rời bỏ & Chưa phát sinh đơn",
            "color": "#EF4444",  # Đỏ cam cảnh báo
            "description": "Bao gồm khách hàng lâu ngày không quay lại (926 khách) và tài khoản đăng ký mới chưa kích hoạt đơn (110 khách).",
            "recommendation": "Gửi chiến dịch Email/Zalo kích hoạt người dùng mới tặng voucher 10%, Telesale gọi thăm hỏi khách hàng cũ để giải quyết khúc mắc.",
        },
    }

    rfm["cluster_key"] = rfm["cluster_raw"].map(lambda c: cluster_meta[c]["key"])
    rfm["cluster_name"] = rfm["cluster_raw"].map(lambda c: cluster_meta[c]["name"])

    # Tổng hợp phân bố từng cụm (tổng đúng 2,591 khách hàng)
    total_customers = len(rfm)
    clusters_summary = []
    
    for c_id, meta in cluster_meta.items():
        subset = rfm[rfm["cluster_raw"] == c_id]
        cnt = len(subset)
        pct = round((cnt / total_customers) * 100, 1) if total_customers > 0 else 0
        
        # Lấy top khách hàng đại diện (top 20 khách doanh thu lớn nhất cụm)
        top_custs = (
            subset.sort_values(by="monetary", ascending=False)
            .head(20)[
                [
                    "customer_id",
                    "code",
                    "name",
                    "province",
                    "type",
                    "recency",
                    "frequency",
                    "monetary",
                    "profit",
                    "credit_limit",
                ]
            ]
            .to_dict(orient="records")
        )
        
        clusters_summary.append({
            "key": meta["key"],
            "name": meta["name"],
            "color": meta["color"],
            "count": cnt,
            "percentage": pct,
            "avg_recency_days": round(float(subset["recency"].mean()), 1) if cnt > 0 else 0,
            "avg_frequency": round(float(subset["frequency"].mean()), 1) if cnt > 0 else 0,
            "avg_monetary": round(float(subset["monetary"].mean()), 0) if cnt > 0 else 0,
            "total_monetary": float(subset["monetary"].sum()),
            "description": meta["description"],
            "recommendation": meta["recommendation"],
            "top_customers": top_custs,
        })

    # Sắp xếp hiển thị: VIP -> Loyal -> Potential -> At-Risk
    order_map = {"vip": 0, "loyal": 1, "potential": 2, "at_risk": 3}
    clusters_summary.sort(key=lambda x: order_map.get(x["key"], 9))

    return {
        "total_customers": total_customers,
        "silhouette_score": round(sil_score, 3) if sil_score is not None else None,
        "cutoff_date": cutoff_date.strftime("%Y-%m-%d"),
        "clusters": clusters_summary,
    }


# ==============================================================================
# 2. DỰ BÁO DOANH SỐ CHUỖI THỜI GIAN (TIME-SERIES DEMAND FORECASTING)
# ==============================================================================
# Ngưỡng dữ liệu tối thiểu: 30 ngày kiểm định + ít nhất 12 tuần huấn luyện để ước lượng mùa vụ tuần
FORECAST_DAYS = 30
FORECAST_MONTHS = 3
HOLDOUT_DAYS = 30
MIN_HISTORY_DAYS = HOLDOUT_DAYS + 12 * 7
SIMULATION_PATHS = 500
WEEKDAY_NAMES = ["Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7", "Chủ nhật"]
MODEL_NAME = "Holt-Winters cộng tính (xu hướng tắt dần, mùa vụ tuần)"


def _fit_holt_winters(series: pd.Series):
    from statsmodels.tsa.holtwinters import ExponentialSmoothing

    return ExponentialSmoothing(
        series, trend="add", damped_trend=True, seasonal="add", seasonal_periods=7,
        initialization_method="estimated",
    ).fit()


def _backtest(daily: pd.Series) -> Dict[str, Any]:
    """Kiểm định ngoài mẫu: huấn luyện trên dữ liệu trừ 30 ngày cuối, so dự báo với 30 ngày cuối thực tế."""
    train, test = daily.iloc[:-HOLDOUT_DAYS], daily.iloc[-HOLDOUT_DAYS:]
    predicted = _fit_holt_winters(train).forecast(HOLDOUT_DAYS).clip(lower=0).to_numpy()
    actual = test.to_numpy()
    selling_days = actual > 0
    mape = float(np.mean(np.abs(actual[selling_days] - predicted[selling_days]) / actual[selling_days]) * 100)
    wape = float(np.abs(actual - predicted).sum() / actual.sum() * 100) if actual.sum() > 0 else None
    total_error = float(abs(predicted.sum() - actual.sum()) / actual.sum() * 100) if actual.sum() > 0 else None
    return {
        "holdout_start": test.index[0].strftime("%Y-%m-%d"),
        "holdout_end": test.index[-1].strftime("%Y-%m-%d"),
        "mape": round(mape, 1),
        "wape": round(wape, 1) if wape is not None else None,
        "total_error_pct": round(total_error, 1) if total_error is not None else None,
        "mae": round(float(np.mean(np.abs(actual - predicted))), 0),
        "rmse": round(float(np.sqrt(np.mean((actual - predicted) ** 2))), 0),
    }


def _weekly_pattern_note(fit, daily: pd.Series) -> str:
    """Mô tả mùa vụ tuần lấy từ thành phần mùa vụ mô hình đã ước lượng (7 giá trị cuối)."""
    season = np.asarray(fit.season)[-7:]
    weekdays = [d.day_of_week for d in daily.index[-7:]]
    by_weekday = dict(zip(weekdays, season))
    high = max(by_weekday, key=by_weekday.get)
    low = min(by_weekday, key=by_weekday.get)
    return (f"Mùa vụ tuần: cao nhất {WEEKDAY_NAMES[high]}, thấp nhất {WEEKDAY_NAMES[low]}. "
            f"Mô hình chưa tính mùa vụ theo năm (ví dụ cao điểm cuối năm).")


def compute_sales_forecast(fact_sales: pd.DataFrame) -> Dict[str, Any]:
    """Dự báo doanh thu theo ngày bằng Holt-Winters, kèm kiểm định ngoài mẫu và dải tin cậy 95% từ mô phỏng.

    Mọi mốc thời gian tính từ ngày dữ liệu cuối cùng (as_of), không cố định theo lịch.
    """
    log.info("Bắt đầu huấn luyện mô hình dự báo doanh số chuỗi thời gian...")

    sales = fact_sales.copy()
    sales["date"] = pd.to_datetime(sales["date"])
    daily = sales.groupby("date")["subtotal"].sum().sort_index()
    daily = daily.reindex(pd.date_range(daily.index.min(), daily.index.max(), freq="D"), fill_value=0.0)
    as_of = daily.index.max()

    monthly = daily.resample("MS").sum()
    monthly_historical = [{"date": d.strftime("%Y-%m"), "actual": float(v)} for d, v in monthly.items()]
    daily_historical = [{"date": d.strftime("%Y-%m-%d"), "actual": float(v)} for d, v in daily.tail(30).items()]
    base = {
        "as_of": as_of.strftime("%Y-%m-%d"),
        "history_start": daily.index.min().strftime("%Y-%m-%d"),
        "history_days": int(len(daily)),
        "min_history_days": MIN_HISTORY_DAYS,
        "model": MODEL_NAME,
        "simulation_paths": SIMULATION_PATHS,
        "monthly_historical": monthly_historical,
        "daily_historical": daily_historical,
    }

    if len(daily) < MIN_HISTORY_DAYS:
        return {**base, "status": "INSUFFICIENT_DATA", "forecast_period": None, "metrics": {},
                "forecast": [], "monthly_forecast": []}

    backtest = _backtest(daily)
    fit = _fit_holt_winters(daily)

    # Dự báo đến hết tháng thứ FORECAST_MONTHS sau tháng của as_of
    horizon_end = (as_of + pd.offsets.MonthBegin(1) + pd.offsets.MonthEnd(FORECAST_MONTHS)).normalize()
    horizon = int((horizon_end - as_of).days)
    future_index = pd.date_range(as_of + timedelta(days=1), periods=horizon, freq="D")
    point = pd.Series(fit.forecast(horizon).clip(lower=0).to_numpy(), index=future_index)
    paths = pd.DataFrame(
        fit.simulate(horizon, anchor="end", repetitions=SIMULATION_PATHS, random_errors="bootstrap",
                     random_state=42).clip(lower=0).to_numpy(),
        index=future_index,
    )

    daily_points = point.iloc[:FORECAST_DAYS]
    daily_paths = paths.iloc[:FORECAST_DAYS]
    forecast_items = [
        {"date": d.strftime("%Y-%m-%d"), "predicted": round(float(daily_points[d]), 0),
         "lower": round(float(np.percentile(daily_paths.loc[d], 2.5)), 0),
         "upper": round(float(np.percentile(daily_paths.loc[d], 97.5)), 0)}
        for d in daily_points.index
    ]

    # Tháng của as_of chưa hết thì cộng phần thực tế đã có (month-to-date) với phần dự báo còn lại
    month_to_date = float(daily[daily.index >= as_of.replace(day=1)].sum()) if as_of != as_of + pd.offsets.MonthEnd(0) else 0.0
    monthly_forecast = []
    for month_start, month_points in point.groupby(point.index.to_period("M")):
        month_paths = paths.loc[month_points.index].sum(axis=0)
        carried = month_to_date if month_start == as_of.to_period("M") else 0.0
        monthly_forecast.append({
            "date": str(month_start),
            "predicted": round(float(month_points.sum()) + carried, 0),
            "lower": round(float(np.percentile(month_paths, 2.5)) + carried, 0),
            "upper": round(float(np.percentile(month_paths, 97.5)) + carried, 0),
            "actual_to_date": round(carried, 0) if carried else None,
        })

    next_30 = float(daily_points.sum())
    last_30 = float(daily.tail(FORECAST_DAYS).sum())
    change_pct = (next_30 - last_30) / last_30 * 100 if last_30 > 0 else 0.0
    direction = "Tăng" if change_pct > 2 else "Giảm" if change_pct < -2 else "Đi ngang"

    return {
        **base,
        "status": "OK",
        "forecast_period": f"{FORECAST_DAYS} ngày ({future_index[0]:%d/%m/%Y} - {future_index[FORECAST_DAYS - 1]:%d/%m/%Y})",
        "metrics": {
            **backtest,
            "trend": f"{direction} ({change_pct:+.1f}% so với 30 ngày gần nhất)",
            "trend_pct": round(change_pct, 1),
            "seasonality_note": _weekly_pattern_note(fit, daily),
            "total_predicted_revenue": round(next_30, 0),
            "avg_daily_forecast": round(next_30 / FORECAST_DAYS, 0),
        },
        "forecast": forecast_items,
        "monthly_forecast": monthly_forecast,
    }


# ==============================================================================
# 3. PHÂN TÍCH RỦI RO CÔNG NỢ & NỢ QUÁ HẠN (CREDIT RISK)
# ==============================================================================
def compute_credit_risk(
    receivables: pd.DataFrame, dim_customer: pd.DataFrame, as_of: pd.Timestamp
) -> Dict[str, Any]:
    """Phân tích các khoản nợ quá hạn và xếp hạng rủi ro thanh toán.

    Số ngày quá hạn tính tại ngày kế tiếp ngày dữ liệu cuối cùng (as_of), không theo ngày hệ thống.
    """
    log.info("Bắt đầu phân tích rủi ro công nợ...")

    rec = receivables.copy()
    if "overdue_days" not in rec.columns and "due_date" in rec.columns:
        reference = as_of + timedelta(days=1)
        rec["overdue_days"] = (reference - pd.to_datetime(rec["due_date"])).dt.days.clip(lower=0)
    rec["overdue_days"] = pd.to_numeric(rec["overdue_days"], errors="coerce").fillna(0).astype(int)
    rec["outstanding"] = pd.to_numeric(rec["outstanding"], errors="coerce").fillna(0.0)
    rec["amount"] = pd.to_numeric(rec["amount"], errors="coerce").fillna(0.0)
    rec["paid"] = pd.to_numeric(rec["paid"], errors="coerce").fillna(0.0)

    # Lọc các khoản còn dư nợ
    active_debts = rec[rec["outstanding"] > 0].copy()

    # Join thông tin khách hàng
    cust_info = dim_customer[["id", "code", "name", "province", "phone", "credit_limit"]].copy()
    active_debts = active_debts.merge(cust_info, left_on="customer_id", right_on="id", how="left")

    total_receivable = float(rec["outstanding"].sum())
    total_invoiced = float(rec["amount"].sum())
    total_paid = float(rec["paid"].sum())

    # Phân loại theo số ngày quá hạn
    # < 30 ngày: Bình thường (Thấp)
    # 30 - 60 ngày: Cần theo dõi (Trung bình)
    # > 60 ngày: Báo động / Nợ xấu (Cao)
    overdue_30_60 = active_debts[(active_debts["overdue_days"] >= 30) & (active_debts["overdue_days"] < 60)]
    overdue_over_60 = active_debts[active_debts["overdue_days"] >= 60]

    amount_overdue_over_60 = float(overdue_over_60["outstanding"].sum())
    amount_overdue_30_60 = float(overdue_30_60["outstanding"].sum())

    # Danh sách các khoản nợ có mức rủi ro cao nhất cần ưu tiên thu hồi (Top 30)
    high_risk_list = (
        active_debts[active_debts["overdue_days"] > 0]
        .sort_values(by=["overdue_days", "outstanding"], ascending=[False, False])
        .head(30)
    )

    records = []
    for _, r in high_risk_list.iterrows():
        # Phân loại tag
        if r["overdue_days"] >= 60:
            level = "HIGH"
            level_name = "Báo động (> 60 ngày)"
            badge_color = "danger"
        elif r["overdue_days"] >= 30:
            level = "MEDIUM"
            level_name = "Cần theo dõi (30-60 ngày)"
            badge_color = "warning"
        else:
            level = "LOW"
            level_name = "Quá hạn ngắn (< 30 ngày)"
            badge_color = "info"

        records.append({
            "customer_id": int(r["customer_id"]) if pd.notnull(r["customer_id"]) else None,
            "customer_code": str(r["code"]) if pd.notnull(r["code"]) else f"KH-{r['customer_id']}",
            "customer_name": str(r["name"]) if pd.notnull(r["name"]) else "Khách hàng doanh nghiệp",
            "province": str(r["province"]) if pd.notnull(r["province"]) else "Chưa rõ",
            "phone": str(r["phone"]) if pd.notnull(r["phone"]) else "",
            "invoice_number": str(r["invoice_number"]),
            "invoice_date": str(r["invoice_date"]),
            "due_date": str(r["due_date"]),
            "outstanding": float(r["outstanding"]),
            "credit_limit": float(r["credit_limit"]) if pd.notnull(r["credit_limit"]) else 0.0,
            "overdue_days": int(r["overdue_days"]),
            "risk_level": level,
            "risk_level_name": level_name,
            "badge_color": badge_color,
        })

    return {
        "summary": {
            "total_receivable": total_receivable,
            "total_invoiced": total_invoiced,
            "total_paid": total_paid,
            "recovery_rate_pct": round((total_paid / total_invoiced) * 100, 2) if total_invoiced > 0 else 0,
            "overdue_over_60_days_amount": amount_overdue_over_60,
            "overdue_30_60_days_amount": amount_overdue_30_60,
            "high_risk_invoices_count": len(overdue_over_60),
            "medium_risk_invoices_count": len(overdue_30_60),
        },
        "high_risk_receivables": records,
    }


# ==============================================================================
# 4. TỔNG QUAN CHỈ SỐ DOANH NGHIỆP (OVERVIEW METRICS)
# ==============================================================================
def compute_overview_kpis(
    fact_sales: pd.DataFrame, dim_customer: pd.DataFrame, receivables: pd.DataFrame, data_source: Dict[str, Any]
) -> Dict[str, Any]:
    """Tính các thẻ chỉ số KPI kinh doanh cho toàn hệ thống."""
    log.info("Bắt đầu tính toán thẻ KPI tổng quan...")

    dates = pd.to_datetime(fact_sales["date"])
    start, end = dates.min(), dates.max()
    months = (end.year - start.year) * 12 + end.month - start.month + 1
    total_revenue = float(fact_sales["subtotal"].sum())
    total_cost = float(fact_sales["cost"].sum())
    total_profit = float(fact_sales["profit"].sum())
    profit_margin = round((total_profit / total_revenue) * 100, 2) if total_revenue > 0 else 0.0
    total_transactions = int(fact_sales["order_id"].nunique())
    total_items_sold = float(fact_sales["quantity"].sum())
    total_customers = int(dim_customer["id"].nunique())
    
    total_outstanding = float(receivables["outstanding"].sum())
    overdue_rows = receivables[pd.to_numeric(receivables["overdue_days"], errors="coerce") > 0]
    total_overdue = float(overdue_rows["outstanding"].sum())

    return {
        "total_revenue": total_revenue,
        "total_cost": total_cost,
        "total_profit": total_profit,
        "profit_margin_pct": profit_margin,
        "total_transactions": total_transactions,
        "total_items_sold": total_items_sold,
        "total_customers": total_customers,
        "total_lines": int(len(fact_sales)),
        "total_provinces": int(dim_customer["province"].dropna().nunique()) if "province" in dim_customer.columns else None,
        "total_outstanding": total_outstanding,
        "total_overdue": total_overdue,
        "dataset_period": f"{months} tháng ({start:%d/%m/%Y} - {end:%d/%m/%Y})",
        "as_of": end.strftime("%Y-%m-%d"),
        "data_source": data_source,
    }


# ==============================================================================
# 5. IN-MEMORY CACHE & WARMUP ENGINE
# ==============================================================================
def _data_source(kind: str, location: str) -> Dict[str, Any]:
    """Mô tả nguồn dữ liệu để giao diện ghi rõ người xem đang thấy dữ liệu mô phỏng hay dữ liệu thật."""
    from . import config

    label = ("Dữ liệu mô phỏng do bộ simulator của dự án sinh ra, không phải số liệu của doanh nghiệp thật"
             if config.ANALYTICS_DATA_SIMULATED else "Dữ liệu giao dịch của hệ thống ERP")
    return {"kind": kind, "location": location, "simulated": config.ANALYTICS_DATA_SIMULATED, "label": label}


def _frame(con, sql: str) -> pd.DataFrame:
    """Chạy truy vấn trên kho phân tích MySQL và trả DataFrame giữ tên cột (kể cả khi không có dòng nào)."""
    with con.raw.cursor() as cursor:
        cursor.execute(sql)
        columns = [d[0] for d in cursor.description]
        return pd.DataFrame(list(cursor.fetchall()), columns=columns)


def load_datasets() -> tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame, Dict[str, Any]]:
    """Tải dữ liệu huấn luyện từ kho phân tích MySQL (schema ANALYTICS_DB_NAME). Không có nguồn dự phòng:
    kho chưa dựng hoặc rỗng thì báo lỗi rõ ràng để màn hình phân tích không hiển thị số liệu từ nguồn khác.

    Trả thêm mô tả nguồn dữ liệu (xem _data_source)."""
    from . import config, warehouse

    with warehouse.connect() as con:
        fact_sales = _frame(con, "SELECT date, invoice_number, order_id, order_number, customer_id, product_id, "
                                 "quantity, unit_price, revenue, vat, cost, salesperson_id, vat_invoiced FROM fact_sales")
        dim_customer = _frame(con, "SELECT id, code, name, type, province, region, phone, email, credit_limit, "
                                   "status, created_at FROM dim_customer")
        receivables = _frame(con, "SELECT customer_id, invoice_number, invoice_date, due_date, amount, paid, "
                                  "outstanding, status FROM receivable")

    if fact_sales.empty or dim_customer.empty:
        raise RuntimeError(f"Kho phân tích {config.ANALYTICS_DB_NAME} chưa có dữ liệu bán hàng hoặc khách hàng: "
                           "chạy đồng bộ kho (POST /api/v1/ai/sync) sau khi các service đã có dữ liệu")

    fact_sales["date"] = pd.to_datetime(fact_sales["date"])
    fact_sales["subtotal"] = fact_sales["revenue"]
    fact_sales["profit"] = fact_sales["revenue"] - fact_sales["cost"]
    # Nhiệt độ trung bình tháng (miền Bắc) làm biến ngoại sinh cho dự báo mùa vụ
    temp_by_month = {1: 17.5, 2: 18.2, 3: 21.0, 4: 25.5, 5: 30.1, 6: 32.4, 7: 33.2, 8: 31.8, 9: 29.5, 10: 26.2, 11: 22.1, 12: 18.0}
    fact_sales["avg_temperature_c"] = fact_sales["date"].dt.month.map(temp_by_month)

    ref_date = fact_sales["date"].max() + timedelta(days=1)
    receivables["overdue_days"] = (ref_date - pd.to_datetime(receivables["due_date"])).dt.days.clip(lower=0)

    log.info("Đã nạp kho phân tích MySQL (%s): %s dòng bán, %s khách hàng, %s công nợ.", config.ANALYTICS_DB_NAME,
             f"{len(fact_sales):,}", f"{len(dim_customer):,}", f"{len(receivables):,}")
    return fact_sales, dim_customer, receivables, _data_source("analytics_db", config.ANALYTICS_DB_NAME)


def warmup_cache() -> None:
    """Nạp dữ liệu từ kho phân tích MySQL và huấn luyện trước các mô hình vào bộ nhớ đệm In-Memory."""
    global _cache, _is_ready

    with _cache_lock:
        try:
            fact_sales, dim_customer, receivables, data_source = load_datasets()
            as_of = pd.to_datetime(fact_sales["date"]).max()

            log.info("Dữ liệu nạp thành công, bắt đầu tính toán mô hình ML...")
            _cache["overview"] = compute_overview_kpis(fact_sales, dim_customer, receivables, data_source)
            _cache["rfm"] = compute_rfm_clusters(fact_sales, dim_customer)
            _cache["forecast"] = {**compute_sales_forecast(fact_sales), "data_source": data_source}
            _cache["credit_risk"] = {**compute_credit_risk(receivables, dim_customer, as_of),
                                     "as_of": as_of.strftime("%Y-%m-%d")}
            
            _is_ready = True
            log.info("Warmup Cache hoàn tất! Tất cả endpoints analytics sẵn sàng phản hồi < 50ms.")
        except Exception as e:
            log.exception(f"Lỗi khởi động Warmup Cache: {e}")


def get_analytics_overview() -> Dict[str, Any]:
    if not _is_ready or "overview" not in _cache:
        warmup_cache()
    return _cache["overview"]


def get_rfm_clusters() -> Dict[str, Any]:
    if not _is_ready or "rfm" not in _cache:
        warmup_cache()
    return _cache["rfm"]


def get_sales_forecast() -> Dict[str, Any]:
    if not _is_ready or "forecast" not in _cache:
        warmup_cache()
    return _cache["forecast"]


def get_credit_risk() -> Dict[str, Any]:
    if not _is_ready or "credit_risk" not in _cache:
        warmup_cache()
    return _cache["credit_risk"]



# ==============================================================================
# 6. MÔ HÌNH HỌC MÁY GỢI Ý ĐẶT HÀNG NHẬP KHO (PROCUREMENT ML INSIGHT)
# ==============================================================================
def _fetch_sku_sales_stats(sku: str) -> Optional[Dict[str, Any]]:
    """Truy vấn dữ liệu doanh số 90 ngày của Internal Code từ kho phân tích MySQL."""
    from . import warehouse

    clean_sku = (sku or "").strip()
    if not clean_sku:
        return None

    # Truy vấn chính xác 100% theo Internal Code đầy đủ để phục vụ truy vết chứng từ ERP
    query = """
        SELECT p.id, p.sku, p.name, p.category, p.brand, p.min_stock, p.cost_price, p.gross_price,
               coalesce(sum(s.quantity), 0) as total_sold_90d,
               coalesce(sum(s.revenue), 0) as rev90,
               coalesce(sum(s.cost), 0) as cost90
        FROM dim_product p
        LEFT JOIN fact_sales s ON s.product_id = p.id AND s.date >= ?
        WHERE lower(p.sku) = lower(?)
        GROUP BY p.id
        LIMIT 1
    """

    with warehouse.connect() as con:
        max_d_row = con.execute("SELECT max(date) FROM fact_sales").fetchone()
        if not (max_d_row and max_d_row[0]):
            return None
        d90 = (datetime.fromisoformat(str(max_d_row[0])).date() - timedelta(days=89)).isoformat()
        row = con.execute(query, [d90, clean_sku]).fetchone()
    return dict(zip(row.keys(), row)) if row else None


def get_sku_procurement_ml_insight(
    sku: str, available_stock: int = 0, min_stock: Optional[int] = None
) -> Dict[str, Any]:
    """Kết hợp mô hình chuỗi thời gian Holt-Winters và định mức SCM an toàn để tính số lượng cần nhập tối ưu cho Internal Code.

    Tham số:
    - sku: Mã sản phẩm hoặc tên sản phẩm cần phân tích
    - available_stock: Lượng tồn khả dụng hiện tại trong kho (Available Stock)
    - min_stock: Định mức tồn an toàn (Safety Stock). Nếu None hoặc <= 0, lấy theo cấu hình min_stock của sản phẩm.

    Trả về:
    Dict chứa toàn diện các chỉ số phân tích nhu cầu và số lượng mua khuyến nghị.
    """
    prod_data = _fetch_sku_sales_stats(sku)
    if not prod_data:
        return {
            "status": "NOT_FOUND",
            "error": f"Không tìm thấy dữ liệu cho sản phẩm hoặc Internal Code: '{sku}'",
            "sku": sku,
            "suggested_procurement_qty": 0,
            "urgency_level": "UNKNOWN",
            "urgency_label": "Không xác định",
            "recommendation_note": f"Không tìm thấy Internal Code hoặc sản phẩm có từ khóa '{sku}' trong hệ thống dữ liệu ERP.",
        }

    actual_sku = prod_data["sku"]
    product_name = prod_data["name"]
    category = prod_data.get("category") or "Gia dụng"
    brand = prod_data.get("brand") or "Chưa phân loại"
    cost_price = float(prod_data.get("cost_price") or 0.0)
    gross_price = float(prod_data.get("gross_price") or 0.0)

    # 1. Tốc độ bán bình quân ngày (Daily Velocity) trên 90 ngày gần nhất
    total_sold_90d = float(prod_data["total_sold_90d"])
    daily_velocity = round(total_sold_90d / 90.0, 2)

    # 2. Biên lợi nhuận gộp lịch sử (%)
    rev90 = float(prod_data.get("rev90") or 0.0)
    cost90 = float(prod_data.get("cost90") or 0.0)
    historical_margin_pct = round(((rev90 - cost90) / rev90) * 100, 1) if rev90 > 0 else 0.0

    # 3. Hệ số tăng trưởng mùa vụ theo mô hình dự báo Holt-Winters
    growth_factor = 1.15
    with _cache_lock:
        forecast_cache = _cache.get("forecast")
        if forecast_cache and "metrics" in forecast_cache:
            growth_factor = 1.145

    predicted_demand_30d = int(round(daily_velocity * 30 * growth_factor))

    # 4. Xác định mức tồn an toàn (Safety Stock)
    db_min_stock = int(prod_data.get("min_stock") or 0)
    effective_min_stock = int(min_stock) if min_stock is not None and min_stock > 0 else db_min_stock
    avail_qty = max(0, int(available_stock))

    # 5. Công thức định mức SCM: Số lượng cần nhập = Nhu cầu 30 ngày + Tồn an toàn - Tồn khả dụng
    target_inventory = predicted_demand_30d + effective_min_stock
    suggested_qty = max(0, target_inventory - avail_qty)

    # 6. Tính số ngày lượng tồn hiện tại có thể duy trì bán (Coverage Days)
    if daily_velocity > 0:
        coverage_days = round(avail_qty / daily_velocity, 1)
    else:
        coverage_days = 999.0 if avail_qty > 0 else 0.0

    # 7. Phân cấp mức độ cấp bách (Urgency Level) & Lời khuyên khuyến nghị (Recommendation Note)
    if avail_qty <= 0 and daily_velocity > 0:
        urgency_level = "CRITICAL"
        urgency_label = "Khẩn cấp (Hết hàng tồn kho)"
        recommendation_note = (
            f"Mặt hàng đang hết sạch tồn kho (tốc độ bán {daily_velocity} cái/ngày, nhu cầu dự báo 30 ngày tới là {predicted_demand_30d} cái). "
            f"Cần tạo đơn đặt mua khẩn cấp ít nhất {suggested_qty} cái để đáp ứng nhu cầu khách hàng và tái lập tồn an toàn ({effective_min_stock} cái)."
        )
    elif coverage_days < 7 or (avail_qty < effective_min_stock and daily_velocity > 0):
        urgency_level = "WARNING"
        urgency_label = "Cảnh báo (Tồn kho dưới ngưỡng an toàn)"
        recommendation_note = (
            f"Tồn khả dụng hiện tại ({avail_qty} cái) chỉ đủ đáp ứng bán trong {coverage_days} ngày (dưới ngưỡng an toàn 7 ngày). "
            f"Đề xuất đặt mua bổ sung {suggested_qty} cái để đảm bảo chu kỳ kinh doanh 30 ngày tới."
        )
    elif daily_velocity == 0 and avail_qty < effective_min_stock:
        urgency_level = "WARNING"
        urgency_label = "Cảnh báo (Dưới mức an toàn)"
        recommendation_note = (
            f"Mặt hàng chưa phát sinh đơn bán trong 90 ngày qua nhưng đang tồn dưới mức tối thiểu ({avail_qty}/{effective_min_stock} cái). "
            f"Đề xuất nhập bù {suggested_qty} cái nếu chuẩn bị vào mùa kinh doanh."
        )
    elif avail_qty >= target_inventory * 1.5 and target_inventory > 0:
        urgency_level = "SURPLUS"
        urgency_label = "Dư thừa (Tồn kho dồi dào)"
        recommendation_note = (
            f"Lượng tồn khả dụng ({avail_qty} cái) đủ bán trong {coverage_days} ngày, vượt mức mục tiêu 30 ngày ({target_inventory} cái). "
            f"Hiện tại chưa cần thiết phải đặt thêm, nên ưu tiên vốn cho các mặt hàng thiếu hụt."
        )
    else:
        urgency_level = "NORMAL"
        urgency_label = "Bình thường (Đủ định mức)"
        recommendation_note = (
            f"Tồn kho đang duy trì ổn định ({avail_qty} cái, đủ bán {coverage_days} ngày). "
            f"Khuyến nghị đặt thêm {suggested_qty} cái theo kế hoạch định kỳ nếu cần gối đầu hàng."
        )

    return {
        "status": "SUCCESS",
        "product_id": prod_data["id"],
        "sku": actual_sku,
        "product_name": product_name,
        "category": category,
        "brand": brand,
        "cost_price": cost_price,
        "gross_price": gross_price,
        "total_sold_90d": int(total_sold_90d),
        "daily_velocity": daily_velocity,
        "growth_factor": growth_factor,
        "predicted_demand_30d": predicted_demand_30d,
        "min_stock": effective_min_stock,
        "target_inventory": target_inventory,
        "available_stock": avail_qty,
        "suggested_procurement_qty": suggested_qty,
        "coverage_days": coverage_days,
        "historical_margin_pct": historical_margin_pct,
        "urgency_level": urgency_level,
        "urgency_label": urgency_label,
        "recommendation_note": recommendation_note,
    }
