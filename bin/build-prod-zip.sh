#!/bin/bash

# Build production zip file for woocommerce-product-feed-for-openai
# Exit on any error
set -e

# Plugin slug constant
PLUGIN_SLUG="woocommerce-product-feed-for-openai"

echo "======================================"
echo "Building Production Zip"
echo "======================================"

echo ""
echo "Step 1: Creating build directory..."
mkdir -p ./build

if [ -d "./build/${PLUGIN_SLUG}" ]; then
    echo "Removing existing ${PLUGIN_SLUG} directory..."
    rm -rf ./build/${PLUGIN_SLUG}
fi

echo ""
echo "Step 2: Creating ${PLUGIN_SLUG} fresh directory..."
mkdir -p ./build/${PLUGIN_SLUG}

echo ""
echo "Step 3: Installing production dependencies..."
COMPOSER_VENDOR_DIR=./vendor_prod composer install --no-dev --optimize-autoloader
mv ./vendor_prod ./build/${PLUGIN_SLUG}/vendor

echo ""
echo "Step 4: Copying files and folders..."
FILES_TO_COPY=(
    "./src"
    "./openai-product-feed-for-woo.php"
)

for file in "${FILES_TO_COPY[@]}"; do
    cp -r "$file" ./build/${PLUGIN_SLUG}
done

echo ""
echo "Step 5: Creating zip file..."
cd ./build
if [ -f "${PLUGIN_SLUG}.zip" ]; then
    echo "Removing existing zip file..."
    rm ${PLUGIN_SLUG}.zip
fi
zip -r ${PLUGIN_SLUG}.zip ${PLUGIN_SLUG}
cd ..

# Success message
echo ""
echo "======================================"
echo "✓ SUCCESS!"
echo "======================================"
echo "Production zip file created successfully!"
echo "Location: ./build/${PLUGIN_SLUG}.zip"
echo "Size: $(du -h ./build/${PLUGIN_SLUG}.zip | awk '{print $1}')"

# Cleanup build directory when not in CI environment
if [ -z "$CI" ]; then
    echo "Removing ./build/${PLUGIN_SLUG} directory"
    rm -rf ./build/${PLUGIN_SLUG}
fi

echo "Done!"
