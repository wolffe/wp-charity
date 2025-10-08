# WP Charity for WooCommerce - How To Guide

This guide will help you understand how to use the WP Charity for WooCommerce plugin to manage fundraising campaigns and allow volunteers to create their own campaigns.

WP Charity for WooCommerce is a powerful WordPress plugin that enables you to create and manage fundraising campaigns with WooCommerce integration. This plugin is perfect for non-profits, charities, and organizations that want to:

- Create fundraising campaigns with donation goals
- Allow volunteers to create their own fundraising campaigns
- Track campaign progress and donations
- Manage multiple campaigns with parent-child relationships
- Accept donations through WooCommerce

## Requirements

Before installing the plugin, ensure your WordPress installation meets these requirements:

- WordPress 6.0 or higher
- WooCommerce 9.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Table of Contents

- [Installation and Configuration](#installation-and-configuration)
  - [Installing the Plugin](#installing-the-plugin)
  - [Configuring Plugin Settings](#configuring-plugin-settings)
- [Creating a New Campaign](#creating-a-new-campaign)
  - [Basic Campaign Creation](#basic-campaign-creation)
  - [Advanced Campaign Settings](#advanced-campaign-settings)
- [Assigning WooCommerce Products to Campaigns](#assigning-woocommerce-products-to-campaigns)
  - [Creating Donation Products](#creating-donation-products)
  - [Linking Products to Campaigns](#linking-products-to-campaigns)
- [Creating Parent Campaigns and Volunteer Campaigns](#creating-parent-campaigns-and-volunteer-campaigns)
  - [Parent Campaign Setup](#parent-campaign-setup)
  - [Volunteer Campaign Management](#volunteer-campaign-management)
- [Setting Up the Account Page](#setting-up-the-account-page)
  - [Creating the Account Page](#creating-the-account-page)
  - [Account Page Features](#account-page-features)
- [How to create a volunteer/fundraiser account page](#how-to-create-a-volunteerfundraiser-account-page)
- [How to create a new campaign/fundraiser](#how-to-create-a-new-campaignfundraiser)
- [How to allow volunteer (child) campaigns](#how-to-allow-volunteer-child-campaigns)

## Installation and Configuration

### Installing the Plugin

#### 1. Download the Plugin

- Log in to your WordPress admin panel
- Navigate to Plugins > Add New
- Search for "WP Charity for WooCommerce"
- Click "Install Now"
- After installation completes, click "Activate"

#### 2. Initial Setup

- After activation, you'll see a new menu item "WP Charity Campaigns" in your admin panel
- The plugin will automatically create necessary database tables and default settings

### Configuring Plugin Settings

#### 1. Access Settings

- Go to Settings > WP Charity in your WordPress admin panel
- You'll see several tabs: **Dashboard** and **Help**

#### 2. General Settings

**Account Page:**
- Create a new page or select an existing one for user account management
- This page will display user profiles and campaign management options
- Recommended page title: "My Account" or "Campaign Dashboard"

**Appearance Settings:**
- **Accent Background Color:**
  - Default: #2f3542
  - Used for buttons, tabs, and primary UI elements
  - Enter a hex color code (e.g., #2f3542)
- **Accent Text Color:**
  - Default: #ecf0f1
  - Used for text on accent-colored elements
  - Enter a hex color code (e.g., #ecf0f1)

**Notification Settings:**
- **Admin Email Notifications:**
  - Enter email addresses to receive notifications
  - Separate multiple emails with commas
  - Default: WordPress admin email
  - You'll receive notifications for:
    - New campaign submissions
    - Campaign status changes
    - Important updates

#### 3. Campaign Settings

**Default Campaign Settings:**
- Set default campaign duration
- Configure default donation goal
- Set up campaign templates (if available)

#### 4. Save Changes

- Click "Save Changes" after configuring each section
- Your settings will be immediately applied

## Creating a New Campaign

### Basic Campaign Creation

#### 1. Access Campaign Creation

- Go to "WP Charity Campaigns" in your WordPress admin menu
- Click "Add New Campaign"
- You'll see the campaign editor interface

#### 2. Campaign Details

**Title:**
- Enter a clear, descriptive title
- This appears as the main heading on your campaign page
- Maximum length: 100 characters

**Content:**
- Use the WordPress editor to create your campaign content
- Include:
  - Campaign description
  - Goals and objectives
  - How donations will be used
  - Impact of contributions
  - You can use images, videos, and formatting options

**Short Description:**
- Write a compelling summary (150-160 characters)
- Appears in search results and campaign listings
- Should include key information about your campaign

**Campaign Settings:**
- **Fundraising Goal:**
  - Enter your target amount
  - Use your site's currency
  - Can be updated later
- **Campaign Duration:**
  - Start Date: When the campaign begins
  - End Date: When the campaign ends
  - Leave empty for ongoing campaigns
- **Campaign Image:**
  - Upload a featured image
  - Recommended size: 1200x630 pixels
  - Format: JPG, PNG, or WebP
  - Maximum file size: 2MB
- **YouTube Video:**
  - Add a video URL to showcase your campaign
  - Format: https://www.youtube.com/watch?v=VIDEO_ID
  - Video will appear on your campaign page

#### 3. Publishing Options

**Status:**
- Draft: Private, not visible to public
- Published: Visible to everyone
- Pending Review: Waiting for admin approval

**Visibility:**
- Public: Anyone can view
- Private: Only logged-in users can view
- Password Protected: Requires password to view

#### 4. Save and Publish

- Click "Save Draft" to save your work
- Click "Publish" when ready to make it public
- Click "Preview" to see how it looks

### Advanced Campaign Settings

#### 1. Campaign Categories

- Organize campaigns by type or cause
- Helps visitors find related campaigns
- Can be used for filtering and sorting

#### 2. Campaign Tags

- Add relevant keywords
- Improves searchability
- Helps with SEO

#### 3. Custom Fields

- Add additional information
- Track specific campaign metrics
- Display custom data on campaign page

## Assigning WooCommerce Products to Campaigns

### Creating Donation Products

#### 1. Product Setup

- Go to Products > Add New
- Select "Simple product" type
- Fill in basic product details:
  - Name: e.g., "Campaign Donation"
  - Description: Explain the donation purpose
  - Price: Set default donation amount

**Product Settings:**
- Regular Price: Set minimum donation amount
- Sale Price: Optional special pricing
- Stock Status: Set to "In Stock"
- Virtual: Check this box
- Downloadable: Uncheck this box

#### 2. Product Variations (Optional)

- Enable variable pricing
- Create preset donation amounts
- Add custom donation options

### Linking Products to Campaigns

#### 1. Campaign-Product Association

- Edit your campaign
- Find the "Campaign Settings" box
- Select your WooCommerce product
- Save the campaign

#### 2. Product Inheritance

- Child campaigns can inherit parent campaign products
- Volunteers can't modify inherited products
- Admins can override product settings

## Creating Parent Campaigns and Volunteer Campaigns

### Parent Campaign Setup

#### 1. Creating a Parent Campaign

- Follow standard campaign creation steps
- In "Campaign Settings":
  - Check "Allow Volunteer Campaigns"
  - Set volunteer campaign guidelines
  - Configure inheritance settings

#### 2. Parent Campaign Settings

**Volunteer Permissions:**
- Allow campaign creation
- Set donation goal limits
- Configure campaign duration limits

**Inheritance Rules:**
- Product settings
- Campaign guidelines
- Donation processing

### Volunteer Campaign Management

#### 1. Volunteer Access

- Volunteers must be logged in
- Access campaign creation through account page
- Select parent campaign when creating

#### 2. Campaign Creation Process

- Log in to volunteer account
- Navigate to "New Campaign" tab
- Fill in campaign details
- Select parent campaign
- Submit for approval

#### 3. Campaign Guidelines

- Follow parent campaign rules
- Set appropriate donation goals
- Provide clear campaign description
- Include relevant images and media

## Setting Up the Account Page

### Creating the Account Page

#### 1. Page Creation

- Go to Pages > Add New
- Title: "My Account" or "Campaign Dashboard"
- Content: Leave empty (plugin will handle content)
- Status: Published
- Save the page

#### 2. Page Configuration

- Go to Settings > WP Charity
- Select your new page as Account Page
- Configure display options:
  - Show user profile
  - Show campaign list
  - Show donation history

### Account Page Features

#### 1. User Profile Section

- Personal information
- Profile picture
- Contact details
- Biography

#### 2. Campaign Management

- List of user's campaigns
- Campaign statistics
- Edit/Delete options
- Campaign status indicators

#### 3. Donation History

- List of donations
- Campaign association
- Amount and date
- Receipt links

## How to create a volunteer/fundraiser account page

Use the shortcode `[fxm-account]` to display a login/signup box if the user is not logged in, and the account page if the user is logged in.

Additional shortcodes:
- `[fxm-login]`
- `[fxm-signup]`

## How to create a new campaign/fundraiser

Use the block editor to design your campaign page.

Use the shortcode below to add a dynamic donation goal progress (if you have any):
```
[donation_box]
```

Use the shortcode below to add a set of buttons:
```
[campaign_buttons]
```

This will display:
- A donation button
- A sharing button
- An optional "Become a Volunteer" button

## How to allow volunteer (child) campaigns

In your campaign editor, tick the "Allow Volunteer Campaigns". This allows volunteers to create their own campaign under the main campaign (or, optionally, independently of any main campaign).