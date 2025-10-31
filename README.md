# WooCommerce Product Feed for OpenAI

A WooCommerce plugin that automatically generates and delivers product feeds to AI platforms like OpenAI's ChatGPT commerce integration.

## What This Plugin Does

**Connects your WooCommerce store to OpenAI's ChatGPT commerce platform** by automatically generating product feeds in the [required format](https://developers.openai.com/commerce/specs/feed) and delivering them via multiple channels.

### Core Features

- **ProductFeed generation abstraction** to support the large store.
- **Complete OpenAI Specification Coverage** includes:
  - All required and optional fields supported
  - Automatic full feed updates every 15 minutes
  - Override global settings for individual products
  - Built-in validation against OpenAI specifications
- **Pos Catalog generation** 

## Quick Start

### Installation

1. **Build and get zip file** from the latest `trunk` branch by either:
   - Running `npm run build` in your local env
   - Running [Build Production Zip](https://github.com/woocommerce/OpenAI-Product-Feed/actions/workflows/build-production-zip.yml) workflow manually for your selected branch
2. **Upload** the zip file via WordPress admin (Plugins > Add New > Upload Plugin) or extract to `/wp-content/plugins/`
3. **Activate** the plugin through WordPress admin
4. Go to **WooCommerce > Settings > Integrations > OpenAI** for OpenAI Product feed.

### Basic Setup

TBA 
