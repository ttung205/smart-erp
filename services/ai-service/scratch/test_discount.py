import sys
import sqlite3
sys.stdout.reconfigure(encoding='utf-8')

con = sqlite3.connect('services/ai-service/data/analytics.db')

rows = con.execute("""
    SELECT c.type, avg(ol.discount), min(ol.discount), max(ol.discount)
    FROM fact_order o
    JOIN fact_order_line ol ON ol.order_id = o.id
    JOIN dim_customer c ON c.id = o.customer_id
    GROUP BY c.type
""").fetchall()

print("Discounts by customer type:")
for r in rows:
    print(r)

