#!/bin/bash

# Build production zip file for OpenAI-Product-Feed
# Exit on any error
set -e

echo "======================================"
echo "Building Production Zip"
echo "======================================"

echo ""
echo "Step 1: Installing production dependencies..."
composer install --no-dev --optimize-autoloader

echo ""
echo "Step 2: Creating build directory..."
mkdir -p ./build

if [ -d "./build/OpenAI-Product-Feed" ]; then
    echo "Removing existing OpenAI-Product-Feed directory..."
    rm -rf ./build/OpenAI-Product-Feed
fi

echo ""
echo "Step 3: Creating OpenAI-Product-Feed fresh directory..."
mkdir -p ./build/OpenAI-Product-Feed

echo ""
echo "Step 4: Copying files and folders..."

# Array of files/directories to copy
FILES_TO_COPY=(
    "./src"
    "./vendor"
    "./openai-product-feed-for-woo.php"
    "./README.md"
)

for file in "${FILES_TO_COPY[@]}"; do
    cp -r "$file" ./build/OpenAI-Product-Feed/
done

echo ""
echo "Step 5: Creating zip file..."
cd ./build
if [ -f "OpenAI-Product-Feed.zip" ]; then
    echo "Removing existing zip file..."
    rm OpenAI-Product-Feed.zip
fi
zip -r OpenAI-Product-Feed.zip OpenAI-Product-Feed
cd ..

# Success message
echo ""
echo "======================================"
echo "✓ SUCCESS!"
echo "======================================"
echo "Production zip file created successfully!"
echo "Location: ./build/OpenAI-Product-Feed.zip"
echo "Size: $(du -h ./build/OpenAI-Product-Feed.zip | awk '{print $1}')"
echo ""
