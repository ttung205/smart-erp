"""Đối tượng nghiệp vụ trong bộ nhớ. Engine chỉ thao tác trên các lớp này;
writers chịu trách nhiệm chuyển sang đúng schema của từng service."""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import date, datetime


@dataclass
class Staff:
    id: int
    name: str
    email: str
    role: str            # tên role trong identity (Sale, Sale Manager, Purchase, ...)
    department: str
    region: str | None = None
    is_new: bool = True  # False = user có sẵn trong identity


@dataclass
class Supplier:
    id: int
    code: str
    name: str
    kind: str            # domestic | import
    supplier_type: str   # MANUFACTURER | TRADING
    group: str
    currency: str
    lead_days: tuple[int, int]
    payment_days: int
    min_order_value: float
    tax_code: str | None
    address: str
    phone: str
    email: str
    contact_person: str
    contact_phone: str
    contact_email: str
    bank_name: str
    bank_account: str
    brands: list[str] = field(default_factory=list)
    notes: str | None = None


@dataclass
class Product:
    id: int
    sku: str
    model: str
    name: str
    category: str
    brand: str
    origin: str
    unit: str
    gross_price: float        # giá niêm yết hiện hành
    cost_price: float         # giá vốn hiện hành
    vat: int
    supplier_id: int
    popularity: float
    spec: str
    launch_year: int | None
    available_from: date
    pack: int                 # bội số đặt hàng
    decay: bool = False       # model cũ, nhu cầu giảm dần
    discontinued_on: date | None = None
    min_stock: int = 0
    guess_error: float = 1.0  # sai số dự báo ban đầu cho model mới
    created_at: datetime | None = None


@dataclass
class Contact:
    name: str
    position: str
    phone: str
    email: str
    is_primary: bool


@dataclass
class Customer:
    id: int
    code: str
    type: str
    name: str
    region: str
    province: str
    address: str
    tax_code: str | None
    phone: str
    email: str
    credit_limit: float
    terms_days: int
    salesperson_id: int
    since: datetime
    discount: float                       # % chiết khấu cơ bản
    budget: float = 0                     # sức mua/tháng
    interval: int = 30
    next_order: date | None = None
    affinity: dict[str, float] = field(default_factory=dict)
    late_payer: bool = False
    pay_cycle: int = 15
    next_pay: date | None = None
    active_until: date | None = None
    contacts: list[Contact] = field(default_factory=list)
    project_dates: list[date] = field(default_factory=list)


@dataclass
class Line:
    id: int
    product: Product
    qty: int
    gross_price: float
    discount: float
    price: float
    vat: int
    delivered: int = 0

    @property
    def subtotal(self) -> float:
        return self.qty * self.price


@dataclass
class Quotation:
    id: int
    number: str
    customer: Customer
    day: date
    created_at: datetime
    staff_id: int
    lines: list[Line]
    status: str = "APPROVED"
    approved_at: datetime | None = None
    order: "Order | None" = None


@dataclass
class Order:
    id: int
    number: str
    customer: Customer
    day: date
    created_at: datetime
    staff_id: int
    lines: list[Line]
    status: str = "PENDING_APPROVAL"
    approved_at: datetime | None = None
    quotation: Quotation | None = None
    customer_po: str | None = None
    updated_at: datetime | None = None

    @property
    def subtotal(self) -> float:
        return sum(l.subtotal for l in self.lines)

    @property
    def vat_total(self) -> float:
        return sum(round(l.subtotal * l.vat / 100, 2) for l in self.lines)


@dataclass
class DeliveryLine:
    id: int
    order_line: Line
    qty: int


@dataclass
class Delivery:
    id: int
    number: str
    order: Order
    day: date
    created_at: datetime
    expected: date
    lines: list[DeliveryLine]
    status: str = "PENDING"


@dataclass
class InvoiceLine:
    id: int
    delivery_line: DeliveryLine
    qty: int

    @property
    def order_line(self) -> Line:
        return self.delivery_line.order_line


@dataclass
class SalesInvoice:
    """Phiếu xuất kho bán hàng (bảng invoices của sales-service)."""
    id: int
    number: str
    delivery: Delivery
    day: date
    created_at: datetime
    created_by: Staff
    lines: list[InvoiceLine]
    shipping_method: str
    carrier: str | None
    picker: Staff
    packer: Staff
    shipper: Staff | None
    posted_at: datetime | None = None
    posted_by: Staff | None = None
    vat_invoice: "VatInvoice | None" = None

    @property
    def subtotal(self) -> float:
        return sum(l.qty * l.order_line.price for l in self.lines)


@dataclass
class VatInvoice:
    id: int
    number: str
    series: str
    day: date
    created_at: datetime
    created_by: Staff
    sales_invoice: SalesInvoice
    subtotal: float
    vat_total: float

    @property
    def total(self) -> float:
        return self.subtotal + self.vat_total


@dataclass
class Receivable:
    id: int
    vat_invoice: VatInvoice
    customer: Customer
    due: date
    amount: float
    paid: float = 0.0

    @property
    def outstanding(self) -> float:
        return round(self.amount - self.paid, 2)


@dataclass
class Receipt:
    """Phiếu thu (PT tiền mặt / BT ngân hàng)."""
    id: int
    number: str
    channel: str             # CASH | BANK
    day: date
    created_at: datetime
    customer: Customer
    amount: float
    staff: Staff
    bank_account_id: int | None
    allocations: list[tuple[Receivable, float]]
    description: str


@dataclass
class POLine:
    id: int
    product: Product
    qty: int
    unit_cost: float          # theo tiền tệ của PO
    tax_rate: float
    received: int = 0
    rejected: int = 0
    invoiced: int = 0
    pr_line: "PRLine | None" = None


@dataclass
class PRLine:
    id: int
    product: Product
    qty: int
    est_cost: float


@dataclass
class Requisition:
    id: int
    code: str
    day: date
    created_at: datetime
    requested_by: Staff
    approved_by: Staff
    approved_at: datetime
    priority: str
    reason: str
    needed_by: date
    lines: list[PRLine]


@dataclass
class PurchaseOrder:
    id: int
    code: str
    supplier: Supplier
    day: date
    created_at: datetime
    created_by: Staff
    approved_by: Staff
    approved_at: datetime
    expected: date
    currency: str
    exchange_rate: float
    lines: list[POLine]
    requisition: Requisition
    status: str = "Approved"
    updated_at: datetime | None = None
    notes: str | None = None

    @property
    def subtotal(self) -> float:
        return sum(l.qty * l.unit_cost for l in self.lines)

    @property
    def tax(self) -> float:
        return sum(l.qty * l.unit_cost * l.tax_rate / 100 for l in self.lines)


@dataclass
class GoodsReceipt:
    id: int
    code: str
    po: PurchaseOrder
    day: date
    created_at: datetime
    received_by: Staff
    delivery_note: str
    lines: list[tuple[POLine, int, int]]   # (dòng PO, nhận, từ chối)


@dataclass
class PurchaseInvoice:
    id: int
    code: str
    po: PurchaseOrder
    receipt: GoodsReceipt
    supplier_invoice_no: str
    day: date
    due: date
    created_at: datetime
    lines: list[tuple[POLine, int]]
    status: str = "Posted"


@dataclass
class StockMove:
    at: datetime
    warehouse_id: int
    product_id: int
    doc_type: str
    doc_id: str
    delta: int
    before: int
    after: int
    notes: str
    created_by: int


@dataclass
class PriceChange:
    at: datetime
    product: Product
    old_gross: float
    new_gross: float
    old_cost: float
    new_cost: float
    reference: str
    staff: Staff
