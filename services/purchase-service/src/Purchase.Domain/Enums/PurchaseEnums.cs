namespace Purchase.Domain.Enums;

public enum RequisitionApprovalStatus
{
    Draft,
    Submitted,
    Approved,
    Rejected,
    Cancelled
}

public enum RequisitionOrderStatus
{
    NotOrdered,
    PartiallyOrdered,
    FullyOrdered
}

public enum RequisitionPriority
{
    Low,
    Medium,
    High,
    Urgent
}

public enum PurchaseOrderStatus
{
    Draft,
    PendingApproval,
    Approved,
    PartiallyReceived,
    Received,
    Completed,
    Cancelled
}

public enum GoodsReceiptStatus
{
    Draft,
    Confirmed,
    Rejected
}

public enum GoodsReceiptSyncStatus
{
    PendingSync,
    Synced,
    Failed
}

public enum PurchaseInvoiceStatus
{
    Draft,
    Posted,
    Paid,
    Cancelled
}

public enum ThreeWayMatchingStatus
{
    Unmatched,
    Matched,
    Discrepancy
}

public enum PrAllocationStatus
{
    Active,
    Released
}

public enum PurchaseReturnStatus
{
    Draft,
    Submitted,
    Approved,
    Dispatched,
    Completed,
    Cancelled
}

public enum PurchaseReturnReason
{
    Defective,
    WrongSpec,
    DamagedTransit,
    Expired,
    Other
}
