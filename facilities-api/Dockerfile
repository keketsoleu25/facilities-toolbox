# -----------------------------
# Stage 1: Build the application
# -----------------------------
FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build

WORKDIR /src

# Copy the project file first and restore dependencies.
# Keeping this separate allows Docker to cache NuGet packages.
COPY ["facilities-api.csproj", "./"]
RUN dotnet restore "facilities-api.csproj"

# Copy the remaining source code.
COPY . .

# Build a production release.
RUN dotnet publish "facilities-api.csproj" -c Release -o /app/publish


# -----------------------------
# Stage 2: Runtime image
# -----------------------------
FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS final

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=build /app/publish .

# The API will listen on port 8080 inside the container.
ENV ASPNETCORE_URLS=http://+:8080
EXPOSE 8080

ENTRYPOINT ["dotnet", "facilities-api.dll"]