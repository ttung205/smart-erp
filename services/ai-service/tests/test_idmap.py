"""Kiểm thử đổi id theo khóa tự nhiên (simulator/idmap.py). Chạy: python -m unittest discover -s tests -t ."""
from __future__ import annotations

import re
import unittest

from simulator import idmap

SIMULATED_USER = re.compile(r"^[a-z]+\.[a-z]+\d+@erp\.local$")


class BuildIdMapsTest(unittest.TestCase):
    def test_ghep_theo_khoa_tu_nhien_khi_id_khac_nhau(self):
        # Số liệu thật: snapshot simulator (id 7, 8...) so với RDS (id 1, 2...)
        source = {"role": {7: "Super Admin", 8: "Sale", 16: "Logistic Manager"}}
        target = {"role": {"Super Admin": 1, "Sale": 2, "Logistic Manager": 11}}

        maps, unmatched = idmap.build_id_maps(source, target)

        self.assertEqual({7: 1, 8: 2, 16: 11}, maps["role"])
        self.assertEqual({}, unmatched["role"])

    def test_khoa_khong_co_o_dich_duoc_bao_la_unmatched(self):
        source = {"department": {3: "Bộ Phận Kho Vận & Quản Lý Tồn", 1: "Phòng Kinh Doanh"}}
        target = {"department": {"Phòng Kinh Doanh": 2}}

        maps, unmatched = idmap.build_id_maps(source, target)

        self.assertEqual({1: 2}, maps["department"])
        self.assertEqual({3: "Bộ Phận Kho Vận & Quản Lý Tồn"}, unmatched["department"])

    def test_id_giu_nguyen_cho_user_mo_phong(self):
        source = {"user": {7: "sale@erp.local", 17: "thang.dh17@erp.local"}}
        target = {"user": {"sale@erp.local": 2}}

        maps, unmatched = idmap.build_id_maps(source, target, keep={"user": {17}})

        self.assertEqual({7: 2, 17: 17}, maps["user"])
        self.assertEqual({}, unmatched["user"])


class RemapRowsTest(unittest.TestCase):
    def setUp(self):
        self.maps = {"role": {7: 1, 8: 2}, "department": {1: 2}, "user": {8: 3, 17: 17}}
        self.unmatched = {"role": {}, "department": {3: "Kho Vận & Quản Lý Tồn"}, "user": {}}

    def test_doi_cot_tham_chieu_va_giu_nguyen_cot_khac(self):
        problems: set[str] = set()

        rows = idmap.remap_rows("identity", "user_roles", ["user_id", "role_id"], [(17, 8), (17, 7)],
                                self.maps, self.unmatched, problems)

        self.assertEqual([(17, 2), (17, 1)], rows)
        self.assertEqual(set(), problems)

    def test_gia_tri_null_duoc_giu_nguyen(self):
        problems: set[str] = set()

        rows = idmap.remap_rows("identity", "users", ["id", "department_id", "line_manager_id"],
                                [(17, None, None)], self.maps, self.unmatched, problems)

        self.assertEqual([(17, None, None)], rows)
        self.assertEqual(set(), problems)

    def test_bang_khong_co_quy_tac_duoc_tra_nguyen_ven(self):
        problems: set[str] = set()

        rows = idmap.remap_rows("catalog", "products", ["id", "name"], [(1, "Quạt")], self.maps, self.unmatched, problems)

        self.assertEqual([(1, "Quạt")], rows)

    def test_gia_tri_khong_ghep_duoc_duoc_gom_vao_problems_thay_vi_nap_sai(self):
        problems: set[str] = set()

        idmap.remap_rows("identity", "users", ["id", "department_id"], [(17, 3), (18, 3)],
                         self.maps, self.unmatched, problems)

        self.assertEqual(
            {"identity.users.department_id: department id 3 (Kho Vận & Quản Lý Tồn) không có trên MySQL đích"}, problems)

    def test_gia_tri_chuoi_trong_cot_nguoi_dung_duoc_giu_nguyen(self):
        problems: set[str] = set()

        rows = idmap.remap_rows("sales", "invoices", ["id", "created_by"], [(1, "Nguyễn Văn A")],
                                self.maps, self.unmatched, problems)

        self.assertEqual([(1, "Nguyễn Văn A")], rows)
        self.assertEqual(set(), problems)

    def test_id_user_khong_co_trong_nguon_van_bi_bao(self):
        problems: set[str] = set()

        idmap.remap_rows("sales", "sales_orders", ["approved_by"], [(99,)], self.maps, self.unmatched, problems)

        self.assertEqual(1, len(problems))
        self.assertIn("user id 99", next(iter(problems)))


class UserConflictTest(unittest.TestCase):
    def test_id_da_thuoc_user_that_la_xung_dot(self):
        problems = idmap.user_id_conflicts({12: "ha.nk12@erp.local"}, {12: "ttung.dev@gmail.com"},
                                           lambda email: bool(SIMULATED_USER.match(email)))

        self.assertEqual(["id user 12 (ha.nk12@erp.local) đã thuộc về ttung.dev@gmail.com trên MySQL đích"], problems)

    def test_user_mo_phong_cu_se_bi_xoa_nen_khong_xung_dot(self):
        problems = idmap.user_id_conflicts({17: "thang.dh17@erp.local"}, {17: "cu.vh17@erp.local"},
                                           lambda email: bool(SIMULATED_USER.match(email)))

        self.assertEqual([], problems)

    def test_id_chua_dung_khong_xung_dot(self):
        self.assertEqual([], idmap.user_id_conflicts({17: "a.b17@erp.local"}, {1: "admin@erp.local"}, lambda e: False))


class RuleConsistencyTest(unittest.TestCase):
    def test_moi_quy_tac_tro_toi_domain_co_that(self):
        for rule, domain in idmap.REMAP_RULES.items():
            self.assertIn(domain, idmap.DOMAINS, f"{rule} trỏ tới domain không tồn tại")

    def test_canh_bao_cot_nghi_van_chua_co_quy_tac(self):
        found = idmap.suspect_columns({
            ("sales", "invoices"): ["id", "customer_id", "posted_by_id", "inventory_warehouse_id"],
            ("purchase", "purchase_orders"): ["id", "created_by", "approved_by", "warehouse_id"],
        })

        self.assertEqual(["sales.invoices.posted_by_id"], found)

    def test_cot_ten_nguoi_va_cot_chuoi_da_biet_khong_bi_canh_bao(self):
        found = idmap.suspect_columns({
            ("sales", "sales_orders"): ["created_by_name", "approved_by_name"],
            ("catalog", "customers"): ["created_by"],
        })

        self.assertEqual([], found)


if __name__ == "__main__":
    unittest.main()
