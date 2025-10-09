# OpenAI Product Feed for WooCommerce

A WooCommerce plugin that automatically generates and delivers product feeds to AI platforms like OpenAI's ChatGPT commerce integration.

## What This Plugin Does

**Connects your WooCommerce store to OpenAI's ChatGPT commerce platform** by automatically generating product feeds in the [required format](https://developers.openai.com/commerce/specs/feed) and delivering them via multiple channels.

### Core Features

- **Complete OpenAI Specification Coverage** - All required and optional fields supported
- **Real-time Updates** - Products sync within 30 seconds of changes
- **Scheduled Delivery** - Automatic full feed updates every 15 minutes  
- **Per-Product Control** - Override global settings for individual products
- **Feed Validation** - Built-in validation against OpenAI specifications
- **Multiple Formats** - JSON, CSV, XML, TSV export options
- **Secure API Access** - Admin-only REST endpoints for integration

## Quick Start

### Installation

1. **Download** the latest release from GitHub Releases
2. **Upload** the zip file via WordPress admin (Plugins > Add New > Upload Plugin) or extract to `/wp-content/plugins/`
3. **Activate** the plugin through WordPress admin
4. Go to **WooCommerce > Settings > OpenAI Feed**

### Basic Setup

1. **Sign Up as OpenAI Merchant**
   - Register at [ChatGPT Merchants](https://chatgpt.com/merchants)
   - Verify your feed meets OpenAI specifications

2. **Configure Global Settings**
   - Set default search/checkout behavior
   - Add merchant information (store name, URLs, policies)
   - Configure shipping and pickup options
   - Configure TOS, Privacy Policy, Return Policy

3. **Set Up Delivery** (Optional)
   - Add OpenAI commerce endpoint URL
   - Enter authentication token
   - Choose feed format (JSON recommended)
   - Enable scheduled delivery

4. **Test Your Feed**
   - Click "Preview Feed" to see generated data
   - Check for validation errors
   - Download sample feed files

## How It Works

### Product Data Mapping

The plugin automatically maps your WooCommerce products to OpenAI's required format:

| WooCommerce Data | OpenAI Field | Notes |
|------------------|--------------|-------|
| Product ID | `id` | Unique identifier |
| Product Name | `title` | HTML stripped |
| Description | `description` | Long or short description |
| Stock Status | `availability` | `in_stock`, `out_of_stock`, `preorder` |
| Weight | `weight` | With unit (kg, lbs, etc.) |
| Images | `image_link`, `additional_image_link` | URLs |
| Categories | `product_category` | Hierarchical path |
| Attributes | `color`, `size`, `material`, etc. | Product attributes |

### Feed Delivery Options

**1. Push to OpenAI (Automatic)**
- Scheduled: Every 15 minutes
- Delta: 30 seconds after product changes
- Manual: On-demand admin trigger

**2. Pull via API (On-demand)**
```
GET /wp-json/wc/v3/openai-feed
GET /wp-json/wc/v3/openai-feed?product_id=123
```

**3. Manual Download**
- Download feeds directly from admin
- Available in JSON, CSV, XML, TSV formats

## Product Control

### Global Settings
Set store-wide defaults for:
- **Search Visibility** - Whether products appear in ChatGPT search
- **Checkout Enabled** - Whether products allow direct purchase
- **Merchant Info** - Store details, policies, shipping

### Per-Product Overrides
Override global settings for specific products:
- **Disable Search** - Exclude from ChatGPT search results
- **Disable Checkout** - Remove direct purchase option
- **Custom Fields** - Add product-specific data (GTIN, MPN, warnings, etc.)

## Required vs Optional Fields

### Required Fields (Always Included)
- `enable_search`, `enable_checkout` - AI platform flags
- `id`, `title`, `description`, `link` - Basic product info
- `gtin`, `brand` - Product identifiers (with fallbacks)
- `availability`, `inventory_quantity` - Stock info
- `weight` - Product weight

### Optional Fields (When Available)
- **Product Details** - MPN, category, material, condition, age group
- **Dimensions** - Length, width, height, combined dimensions
- **Media** - Additional images, videos, 3D models
- **Pricing** - Sale prices, promotional dates
- **Variants** - Colors, sizes, gender targeting
- **Shipping** - Methods, pickup options, SLA
- **Compliance** - Warnings, age restrictions, Q&A

## Access


### Endpoints
```http
# Get complete feed
GET /wp-json/wc/v3/openai-feed

# Preview specific product
GET /wp-json/wc/v3/openai-feed?product_id=123

# Response format
Content-Type: application/json
[
  {
    "enable_search": "true",
    "id": "123",
    "title": "Product Name",
    "availability": "in_stock",
    "weight": "1.5 kg"
    // ... all mapped fields
  }
]
```

## Validation & Troubleshooting

### Feed Validation
- **Automatic** - Validates before every delivery
- **Manual** - Test via "Preview Feed" button
- **Logged** - All validation errors logged to WooCommerce logs

### Common Issues

**Missing Required Fields**
- GTIN → Add `_gtin` custom field or uses fallback `'MISSING'`
- Brand → Add `pa_brand` attribute or uses fallback `'Generic'`

**Validation Errors**
- Check WooCommerce > Status > Logs (source: oapfw)
- Verify product data completeness
- Test with single product preview

**Delivery Issues**
- Confirm endpoint URL and token
- Check WooCommerce logs for HTTP errors
- Verify network connectivity


### Getting Help
- Check WooCommerce > Status > Logs (source: oapfw)
- Use "Preview Feed" for testing individual products
- Verify all required WooCommerce data is present

### Logs Location
WooCommerce > Status > Logs > Filter by source: `oapfw`
