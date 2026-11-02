import sys
import sqlite3
sys.stdout.reconfigure(encoding='utf-8')

con = sqlite3.connect('services/ai-service/data/analytics.db')
tables = [r[0] for r in con.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()]
print('Tables:', tables)

for table in ['dim_customer', 'receivable', 'fact_order', 'fact_order_line', 'dim_product', 'stock']:
    if table in tables:
        cols = [r[1] for r in con.execute(f"PRAGMA table_info({table})").fetchall()]
        sample = con.execute(f"SELECT * FROM {table} LIMIT 2").fetchall()
        print(f"\n--- {table} ---")
        print("Columns:", cols)
        print("Sample:", sample)

cust_samples = con.execute("SELECT id, code, name, type, credit_limit FROM dim_customer LIMIT 5").fetchall()
print("\nCustomer samples:", cust_samples)

# Check distinct customer types
types = con.execute("SELECT DISTINCT type, count(*) FROM dim_customer GROUP BY type").fetchall()
print("\nCustomer types count:", types)

# Check receivable sample
rec_samples = con.execute("SELECT * FROM receivable LIMIT 5").fetchall()
print("\nReceivable sample:", rec_samples)
