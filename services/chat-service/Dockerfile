# Stage 1 (build): restore + publish
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /src

# Copy project definition files for layer caching
COPY src/Chat.Domain/Chat.Domain.csproj src/Chat.Domain/
COPY src/Chat.Application/Chat.Application.csproj src/Chat.Application/
COPY src/Chat.Infrastructure/Chat.Infrastructure.csproj src/Chat.Infrastructure/
COPY src/Chat.Api/Chat.Api.csproj src/Chat.Api/

RUN dotnet restore src/Chat.Api/Chat.Api.csproj

# Copy remaining source code and publish
COPY src/ src/
RUN dotnet publish src/Chat.Api/Chat.Api.csproj -c Release -o /app/publish /p:UseAppHost=false

# Stage 2 (test): docker build --target test, chạy test bằng scripts/test.ps1. Có SDK + thư mục tests/.
FROM build AS test
COPY ChatService.slnx ./
COPY tests/ tests/
CMD ["dotnet", "test", "--nologo", "--verbosity", "minimal"]

# Stage 3 (runtime): stage cuối cùng = image chạy thật, chỉ có runtime + kết quả publish
FROM mcr.microsoft.com/dotnet/aspnet:10.0 AS runtime
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=build /app/publish .

# Thu muc luu tep dinh kem (volume chat_files)
RUN mkdir -p /app/chat-files

ENV ASPNETCORE_URLS=http://+:8009
ENV ASPNETCORE_ENVIRONMENT=Production
EXPOSE 8009

ENTRYPOINT ["dotnet", "Chat.Api.dll"]
