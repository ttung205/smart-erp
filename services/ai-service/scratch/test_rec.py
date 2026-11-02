import sys
import sqlite3
sys.stdout.reconfigure(encoding='utf-8')

con = sqlite3.connect('services/ai-service/data/analytics.db')

rows = con.execute("""
    SELECT c.code, c.name, c.type, c.credit_limit,
           coalesce(sum(r.outstanding), 0) as total_debt,
           coalesce(sum(case when r.due_date < '2026-10-31' and r.outstanding > 0 then r.outstanding else 0 end), 0) as overdue_debt
    FROM dim_customer c
    JOIN receivable r ON r.customer_id = c.id
    WHERE r.outstanding > 0
    GROUP BY c.id
    ORDER BY total_debt DESC
    LIMIT 5
""").fetchall()

print("Customers with outstanding debt:")
for r in rows:
    print(r)

