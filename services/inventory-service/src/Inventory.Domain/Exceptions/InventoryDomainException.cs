namespace Inventory.Domain.Exceptions;

/// <summary>
/// Base exception for all business/domain-level validation errors in Inventory Service.
/// </summary>
public abstract class InventoryDomainException : Exception
{
    public string ErrorCode { get; }

    protected InventoryDomainException(string errorCode, string message) : base(message)
    {
        ErrorCode = errorCode;
    }

    protected InventoryDomainException(string errorCode, string message, Exception innerException) 
        : base(message, innerException)
    {
        ErrorCode = errorCode;
    }
}
