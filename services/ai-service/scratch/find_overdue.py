import sys
import sqlite3
sys.stdout.reconfigure(encoding='utf-8')

con = sqlite3.connect('services/ai-service/data/analytics.db')
rows = con.execute("""
    SELECT c.id, c.code, c.name, c.type, c.credit_limit,
           sum(r.outstanding) as total_debt,
           sum(case when r.due_date < '2026-09-30' and r.outstanding > 0 then r.outstanding else 0 end) as overdue_debt
    FROM dim_customer c
    JOIN receivable r ON r.customer_id = c.id
    WHERE r.outstanding > 0
    GROUP BY c.id
    HAVING overdue_debt > 0
    LIMIT 5
""").fetchall()

print("Customers with overdue debt as of 2026-09-30:")
for r in rows:
    print(r)

