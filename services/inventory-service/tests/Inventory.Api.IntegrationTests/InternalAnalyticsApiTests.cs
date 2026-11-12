using System.Net;
using System.Text.Json;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc.Testing;
using Microsoft.Extensions.Configuration;
using Xunit;

namespace Inventory.Api.IntegrationTests;

public class InternalAnalyticsApiTests : IClassFixture<InventoryApiFactory>
{
    private readonly HttpClient _client;

    public InternalAnalyticsApiTests(InventoryApiFactory factory)
    {
        _client = factory.WithWebHostBuilder(builder =>
        {
            builder.ConfigureAppConfiguration((_, configuration) =>
            {
                configuration.AddInMemoryCollection(new Dictionary<string, string?>
                {
                    ["Jwt:Enabled"] = "false"
                });
            });
        }).CreateClient();
    }

    [Theory]
    [InlineData("/internal/analytics/stock-balances")]
    [InlineData("/internal/analytics/reservation-balances")]
    [InlineData("/internal/analytics/stock-moves")]
    public async Task Export_returns_envelope_with_paging_fields(string path)
    {
        var response = await _client.GetAsync(path + "?limit=5");

        Assert.Equal(HttpStatusCode.OK, response.StatusCode);
        using var doc = JsonDocument.Parse(await response.Content.ReadAsStringAsync());
        var root = doc.RootElement;
        Assert.True(root.GetProperty("success").GetBoolean());
        Assert.True(root.GetProperty("data").GetProperty("items").GetArrayLength() <= 5);
        Assert.True(root.GetProperty("data").TryGetProperty("next_after_id", out _));
    }
}
