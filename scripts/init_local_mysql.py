import pymysql

conn = pymysql.connect(host='127.0.0.1', port=3306, user='root', password='123456')
with conn.cursor() as cur:
    dbs = [
        'erp_identity_db',
        'erp_catalog_db',
        'erp_purchase_db',
        'erp_inventory_db',
        'erp_sales_db',
        'erp_eoffice_db',
        'erp_analytics_db',
        'erp_finance_db',
        'erp_chat_db'
    ]
    cur.execute("CREATE USER IF NOT EXISTS 'erp_user'@'%' IDENTIFIED BY 'erp_secret'")
    cur.execute("CREATE USER IF NOT EXISTS 'erp_user'@'localhost' IDENTIFIED BY 'erp_secret'")
    cur.execute("ALTER USER 'erp_user'@'%' IDENTIFIED BY 'erp_secret'")
    cur.execute("ALTER USER 'erp_user'@'localhost' IDENTIFIED BY 'erp_secret'")

    for db_name in dbs:
        cur.execute(f"CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
        cur.execute(f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO 'erp_user'@'%'")
        cur.execute(f"GRANT ALL PRIVILEGES ON `{db_name}`.* TO 'erp_user'@'localhost'")

    cur.execute("FLUSH PRIVILEGES")

conn.commit()
conn.close()
print("All ERP databases and privileges created successfully!")
