import sys
import json
import time
import base64
import hmac
import hashlib
import requests

sys.stdout.reconfigure(encoding='utf-8')

def make_jwt(payload: dict, secret: str) -> str:
    header = {"alg": "HS256", "typ": "JWT"}
    h_b64 = base64.urlsafe_b64encode(json.dumps(header).encode()).decode().rstrip("=")
    p_b64 = base64.urlsafe_b64encode(json.dumps(payload).encode()).decode().rstrip("=")
    signing_input = f"{h_b64}.{p_b64}".encode()
    sig = hmac.new(secret.encode(), signing_input, hashlib.sha256).digest()
    sig_b64 = base64.urlsafe_b64encode(sig).decode().rstrip("=")
    return f"{h_b64}.{p_b64}.{sig_b64}"

secret = "smart_erp_super_secure_jwt_secret_key_2026"
payload = {
    "sub": "1",
    "name": "Admin User",
    "email": "admin@erp.local",
    "roles": ["SUPER_ADMIN"],
    "permissions": ["ai.use", "finance.view"],
    "iss": "identity-service",
    "exp": int(time.time()) + 3600
}
token = make_jwt(payload, secret)
headers = {"Authorization": f"Bearer {token}"}

# 1. Overview
r = requests.get("http://127.0.0.1:8010/api/v1/ai/analytics/overview", headers=headers)
ov = r.json().get("data", {})
print("1. Overview Status:", r.status_code, "| Total Customers:", ov.get("total_customers"))

# 2. RFM Clusters
r = requests.get("http://127.0.0.1:8010/api/v1/ai/analytics/rfm-clusters", headers=headers)
data = r.json().get("data", {})
print("\n2. RFM Clusters Status:", r.status_code, "| Total:", data.get("total_customers"))
total_cnt = 0
for c in data.get("clusters", []):
    print(f"  - {c['key']} ({c['name']}): count={c['count']} ({c['percentage']}%), avg_monetary={c['avg_monetary']:,.0f} đ")
    total_cnt += c['count']
print(f"  => TỔNG CỘNG 4 NHÓM: {total_cnt} / 2591 đối tác (Khớp 100%: {total_cnt == 2591})")

# 3. Forecast
r = requests.get("http://127.0.0.1:8010/api/v1/ai/analytics/forecast", headers=headers)
fdata = r.json().get("data", {})
print("\n3. Demand Forecast Status:", r.status_code, "| MAPE:", fdata.get("metrics", {}).get("mape"))
print("First 7 forecast days:")
for item in fdata.get("forecast", [])[:7]:
    print(f"  {item['date']}: Dự báo = {item['predicted']:,.0f} ₫ | Dải tin cậy 95% = [{item['lower']:,.0f} ₫ - {item['upper']:,.0f} ₫]")
