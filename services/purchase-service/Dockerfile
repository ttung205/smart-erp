# Stage 1: Build
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /src

# Copy project definition files for layer caching
COPY src/Purchase.Domain/Purchase.Domain.csproj src/Purchase.Domain/
COPY src/Purchase.Application/Purchase.Application.csproj src/Purchase.Application/
COPY src/Purchase.Infrastructure/Purchase.Infrastructure.csproj src/Purchase.Infrastructure/
COPY src/Purchase.Api/Purchase.Api.csproj src/Purchase.Api/

RUN dotnet restore src/Purchase.Api/Purchase.Api.csproj

# Copy remaining source code and publish
COPY src/ src/
RUN dotnet publish src/Purchase.Api/Purchase.Api.csproj -c Release -o /app/publish /p:UseAppHost=false

# Stage 2: Runtime
FROM mcr.microsoft.com/dotnet/aspnet:10.0 AS runtime
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=build /app/publish .

ENV ASPNETCORE_URLS=http://+:8003
ENV ASPNETCORE_ENVIRONMENT=Production
EXPOSE 8003

ENTRYPOINT ["dotnet", "Purchase.Api.dll"]
