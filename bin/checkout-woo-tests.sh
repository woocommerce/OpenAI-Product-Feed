#!/bin/bash

if [ -d "tests/unit/woo" ]; then
    echo "WooCommerce tests already checked out"
    exit 0
fi

git clone --depth 1 --filter=blob:none --sparse https://github.com/woocommerce/woocommerce.git tests/unit/woo
cd tests/unit/woo
git sparse-checkout set --no-cone plugins/woocommerce/tests/**
