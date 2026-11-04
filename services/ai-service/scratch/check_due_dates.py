import sys
import os
import sqlite3
sys.stdout.reconfigure(encoding='utf-8')

con = sqlite3.connect('services/ai-service/data/analytics.db')
rows = con.execute("""
    SELECT invoice_number, invoice_date, due_date, outstanding, status
    FROM receivable
    WHERE customer_id = 15 AND outstanding > 0
""").fetchall()
for r in rows:
    print(r)

