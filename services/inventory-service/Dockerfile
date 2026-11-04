# Stage 1 (build): restore + publish
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /src

# Copy project definition files for layer caching
COPY src/Inventory.Domain/Inventory.Domain.csproj src/Inventory.Domain/
COPY src/Inventory.Application/Inventory.Application.csproj src/Inventory.Application/
COPY src/Inventory.Infrastructure/Inventory.Infrastructure.csproj src/Inventory.Infrastructure/
COPY src/Inventory.Api/Inventory.Api.csproj src/Inventory.Api/

RUN dotnet restore src/Inventory.Api/Inventory.Api.csproj

# Copy remaining source code and publish
COPY src/ src/
RUN dotnet publish src/Inventory.Api/Inventory.Api.csproj -c Release -o /app/publish /p:UseAppHost=false

# Stage 2 (test): docker build --target test, chạy test bằng scripts/test.ps1. Có SDK + thư mục tests/.
FROM build AS test
COPY InventoryService.slnx ./
COPY tests/ tests/
CMD ["dotnet", "test", "--nologo", "--verbosity", "minimal"]

# Stage 3 (runtime): stage cuối cùng = image chạy thật, chỉ có runtime + kết quả publish
FROM mcr.microsoft.com/dotnet/aspnet:10.0 AS runtime
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=build /app/publish .

ENV ASPNETCORE_URLS=http://+:8004
ENV ASPNETCORE_ENVIRONMENT=Production
EXPOSE 8004

ENTRYPOINT ["dotnet", "Inventory.Api.dll"]
