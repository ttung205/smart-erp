using System.Net;
using System.Net.Http.Json;
using System.Text.Json;
using Inventory.Application.Common.Models;
using Inventory.Application.DTOs;
using Microsoft.AspNetCore.Mvc.Testing;
using Xunit;

namespace Inventory.Api.IntegrationTests;

public class StocksApiTests : IClassFixture<WebApplicationFactory<Program>>
{
    private readonly HttpClient _client;
    private readonly JsonSerializerOptions _jsonOptions;

    public StocksApiTests(WebApplicationFactory<Program> factory)
    {
        _client = factory.CreateClient();
        _jsonOptions = new JsonSerializerOptions
        {
            PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower
        };
    }

    [Fact]
    public async Task HealthCheck_ShouldReturn200()
    {
        var response = await _client.GetAsync("/health");
        Assert.Equal(HttpStatusCode.OK, response.StatusCode);

        var content = await response.Content.ReadAsStringAsync();
        Assert.Contains("healthy", content);
    }

    [Fact]
    public async Task CorrelationId_ShouldBeReturnedInResponseHeader()
    {
        var customCorrId = "test_corr_abc_123";
        var request = new HttpRequestMessage(HttpMethod.Get, "/api/v1/inventory/stocks");
        request.Headers.Add("X-Correlation-ID", customCorrId);

        var response = await _client.SendAsync(request);

        Assert.Equal(HttpStatusCode.OK, response.StatusCode);
        Assert.True(response.Headers.Contains("X-Correlation-ID"));
        Assert.Equal(customCorrId, response.Headers.GetValues("X-Correlation-ID").First());
    }

    [Fact]
    public async Task GetStocks_ShouldReturn200_WithEnvelopeAndPagination()
    {
        var response = await _client.GetAsync("/api/v1/inventory/stocks?page=1&pageSize=10");
        Assert.Equal(HttpStatusCode.OK, response.StatusCode);

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<List<StockResponseDto>>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.True(envelope.Success);
        Assert.NotNull(envelope.Data);
        Assert.NotNull(envelope.Meta);
        Assert.NotNull(envelope.Meta.Pagination);
        Assert.Equal(1, envelope.Meta.Pagination.CurrentPage);
    }

    [Fact]
    public async Task GetStockByProductId_Existing_ShouldReturn200_WithOverview()
    {
        // Product 1 được nạp sẵn từ Seed data (MacBook Pro)
        var response = await _client.GetAsync("/api/v1/inventory/stocks/1");
        Assert.Equal(HttpStatusCode.OK, response.StatusCode);

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<ProductStockOverviewDto>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.True(envelope.Success);
        Assert.NotNull(envelope.Data);
        Assert.Equal(1, envelope.Data.ProductId);
        Assert.True(envelope.Data.TotalOnHand > 0);
        Assert.NotEmpty(envelope.Data.Warehouses);
    }

    [Fact]
    public async Task GetStockByProductId_NotFound_ShouldReturn404_WithEnvelope()
    {
        var response = await _client.GetAsync("/api/v1/inventory/stocks/999999");
        Assert.Equal(HttpStatusCode.NotFound, response.StatusCode);

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<object>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.False(envelope.Success);
        Assert.NotNull(envelope.Error);
        Assert.Equal("RESOURCE_NOT_FOUND", envelope.Error.Code);
    }

    [Fact]
    public async Task ReserveStock_Sufficient_ShouldReturn200_AndEnvelope()
    {
        var request = new ReserveStockRequest
        {
            OrderId = "SO-API-TEST-001",
            WarehouseId = 1,
            ProductId = 1,
            Quantity = 2
        };

        var response = await _client.PostAsJsonAsync("/api/v1/inventory/stocks/reserve", request, _jsonOptions);
        Assert.Equal(HttpStatusCode.OK, response.StatusCode);

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<StockOperationResponseDto>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.True(envelope.Success);
        Assert.NotNull(envelope.Data);
        Assert.Equal("SO_RESERVE", envelope.Data.OperationType);
        Assert.Equal(2, envelope.Data.QuantityDelta);
    }

    [Fact]
    public async Task ReserveStock_InsufficientStock_ShouldReturn409_Conflict_WithInsufficientStockCode()
    {
        var request = new ReserveStockRequest
        {
            OrderId = "SO-API-FAIL-001",
            WarehouseId = 1,
            ProductId = 1,
            Quantity = 999999 // Vượt tồn
        };

        var response = await _client.PostAsJsonAsync("/api/v1/inventory/stocks/reserve", request, _jsonOptions);
        Assert.Equal(HttpStatusCode.Conflict, response.StatusCode); // HTTP 409

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<object>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.False(envelope.Success);
        Assert.NotNull(envelope.Error);
        Assert.Equal("INSUFFICIENT_STOCK", envelope.Error.Code);
    }

    [Fact]
    public async Task ReceiveStock_ShouldReturn200_AndIncreaseOnHand()
    {
        var request = new ReceiveStockRequest
        {
            ReferenceDoc = "PO-API-RECEIPT-001",
            WarehouseId = 1,
            ProductId = 1,
            Quantity = 10,
            MinStock = 5
        };

        var response = await _client.PostAsJsonAsync("/api/v1/inventory/stocks/receive", request, _jsonOptions);
        Assert.Equal(HttpStatusCode.OK, response.StatusCode);

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<StockOperationResponseDto>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.True(envelope.Success);
        Assert.NotNull(envelope.Data);
        Assert.Equal("PO_RECEIPT", envelope.Data.OperationType);
        Assert.Equal(10, envelope.Data.QuantityDelta);
    }

    [Fact]
    public async Task GetMovements_ShouldReturn200_WithAuditList()
    {
        var response = await _client.GetAsync("/api/v1/inventory/stocks/movements?page=1&pageSize=10");
        Assert.Equal(HttpStatusCode.OK, response.StatusCode);

        var envelope = await response.Content.ReadFromJsonAsync<ApiEnvelope<List<StockMovementResponseDto>>>(_jsonOptions);
        Assert.NotNull(envelope);
        Assert.True(envelope.Success);
        Assert.NotNull(envelope.Data);
        Assert.NotEmpty(envelope.Data);
    }
}
