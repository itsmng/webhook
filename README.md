# Webhook Plugin

A plugin for ITSM-NG that enables webhook notifications for various events.

## Features

- Send webhook notifications for tickets, changes, and problems
- Configurable HTTP methods (POST, PUT, PATCH, GET)
- Custom headers support
- Template-based payload customization
- Multi-language support

## Installation

1. Install the plugin in the ITSM-NG plugins directory
2. Install dependencies: `composer install --no-dev`
3. Activate the plugin in ITSM-NG administration
4. Configure webhook endpoints and templates

## Configuration

- Access webhook configuration via Setup > Webhook
- Create webhook endpoints with custom URLs and headers
- Define templates for different item types (Ticket, Change, Problem)
- Set up notification events to trigger webhooks
