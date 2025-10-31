#!/bin/bash

# Build production zip file for woocommerce-product-feed-for-openai
# Exit on any error
set -e

echo "======================================"
echo "Building Production Zip"
echo "======================================"

echo ""
echo "Step 1: Creating build directory..."
mkdir -p ./build

if [ -d "./build/woocommerce-product-feed-for-openai" ]; then
    echo "Removing existing woocommerce-product-feed-for-openai directory..."
    rm -rf ./build/woocommerce-product-feed-for-openai
fi

echo ""
echo "Step 2: Creating woocommerce-product-feed-for-openai fresh directory..."
mkdir -p ./build/woocommerce-product-feed-for-openai

echo ""
echo "Step 3: Installing production dependencies..."
COMPOSER_VENDOR_DIR=./build/woocommerce-product-feed-for-openai/vendor composer install --no-dev --optimize-autoloader

echo ""
echo "Step 4: Copying files and folders..."
FILES_TO_COPY=(
    "./src"
    "./openai-product-feed-for-woo.php"
)

for file in "${FILES_TO_COPY[@]}"; do
    cp -r "$file" ./build/woocommerce-product-feed-for-openai
done

echo ""
echo "Step 5: Creating zip file..."
cd ./build
if [ -f "woocommerce-product-feed-for-openai.zip" ]; then
    echo "Removing existing zip file..."
    rm woocommerce-product-feed-for-openai.zip
fi
zip -r woocommerce-product-feed-for-openai.zip woocommerce-product-feed-for-openai
cd ..

# Success message
echo ""
echo "======================================"
echo "✓ SUCCESS!"
echo "======================================"
echo "Production zip file created successfully!"
echo "Location: ./build/woocommerce-product-feed-for-openai.zip"
echo "Size: $(du -h ./build/woocommerce-product-feed-for-openai.zip | awk '{print $1}')"

# Cleanup build directory when not in CI environment
if [ -z "$CI" ]; then
    echo "Removing ./build/woocommerce-product-feed-for-openai directory"
    rm -rf ./build/woocommerce-product-feed-for-openai
fi

echo "Done!"
