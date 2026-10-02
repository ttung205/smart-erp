namespace Purchase.Domain.Exceptions;

public class PurchaseDomainException : Exception
{
    public string ErrorCode { get; }

    public PurchaseDomainException(string errorCode, string message) : base(message)
    {
        ErrorCode = errorCode;
    }

    public PurchaseDomainException(string errorCode, string message, Exception innerException) : base(message, innerException)
    {
        ErrorCode = errorCode;
    }
}
