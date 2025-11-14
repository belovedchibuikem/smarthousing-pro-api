# Blockchain Integration Guide - Tenant-Specific Setup

This guide explains how to set up blockchain integration for your tenant organization. **Each tenant has their own blockchain configuration**, allowing different businesses to use different networks, wallets, and settings.

## Overview

The blockchain system is **tenant-specific**, meaning:
- ✅ Each tenant configures their own blockchain networks
- ✅ Each tenant manages their own wallets
- ✅ Each tenant uses their own API keys
- ✅ Settings are isolated per tenant
- ✅ No sharing of credentials between tenants

## Quick Start - Setup Wizard

The easiest way to set up blockchain is through the **Setup Wizard**:

1. Navigate to **Admin → Blockchain → Setup** (`/admin/blockchain/setup`)
2. Complete the 5-step wizard:
   - **Step 1:** Configure network RPC URLs
   - **Step 2:** Add explorer API keys
   - **Step 3:** Add smart contract addresses (optional)
   - **Step 4:** Import a wallet
   - **Step 5:** Finalize settings

The wizard will guide you through each step and test connections automatically.

## Detailed Setup Guide

### Step 1: Network Configuration

Configure RPC URLs for blockchain networks. Each tenant can:
- Use public RPC endpoints
- Use their own Infura/Alchemy endpoints (recommended for production)
- Override defaults for specific networks

**Example RPC URLs:**
```
Ethereum: https://mainnet.infura.io/v3/YOUR_INFURA_API_KEY
Polygon: https://polygon-mainnet.infura.io/v3/YOUR_INFURA_API_KEY
BSC: https://bsc-dataseed.binance.org
Arbitrum: https://arbitrum-mainnet.infura.io/v3/YOUR_INFURA_API_KEY
Optimism: https://optimism-mainnet.infura.io/v3/YOUR_INFURA_API_KEY
```

**To get Infura API Key:**
1. Sign up at https://infura.io
2. Create a new project
3. Copy your API key
4. Use it in RPC URLs

### Step 2: Explorer API Keys

Get free API keys from blockchain explorers for transaction verification:

- **Etherscan:** https://etherscan.io/apis (for Ethereum)
- **PolygonScan:** https://polygonscan.com/apis (for Polygon)
- **BSCScan:** https://bscscan.com/apis (for BSC)
- **Arbiscan:** https://arbiscan.io/apis (for Arbitrum)
- **Optimistic Etherscan:** https://optimistic.etherscan.io/apis (for Optimism)

These are **free** and take only a few minutes to obtain. They enable automatic transaction verification.

### Step 3: Smart Contract Addresses (Optional)

If you've deployed property registry smart contracts, add their addresses here. The system will use these for property registration.

**Contract Requirements:**
Your smart contract should implement a function to register properties:

```solidity
function registerProperty(bytes32 propertyHash, bytes calldata propertyData) external {
    // Your implementation
}
```

If you haven't deployed contracts yet, leave this step empty. The system will use a fallback method for property registration.

### Step 4: Wallet Setup

Import a wallet that will be used to sign blockchain transactions.

**Requirements:**
- Wallet must have native tokens (ETH, MATIC, BNB, etc.) for gas fees
- Private key will be encrypted and stored securely
- First wallet for each network becomes the default wallet

**To create a wallet:**
1. Use MetaMask or similar wallet
2. Export private key (be very careful!)
3. Import it in the wizard

**Security Note:**
- Private keys are encrypted using Laravel's encryption
- Never share your private key
- Consider using a dedicated wallet for property registrations
- Keep backup of wallet mnemonic phrase securely

### Step 5: Complete Setup

Finalize configuration:
- **Webhooks:** Enable for real-time transaction notifications (optional)
- **Gas Settings:** Adjust gas price multiplier and default gas limit
- Click "Complete Setup" to finish

## Managing Wallets

After initial setup, manage wallets at **Admin → Blockchain → Wallets** (`/admin/blockchain/wallets`):

### Features:
- **Import Additional Wallets:** Add more wallets for different networks
- **Set Default Wallet:** Choose which wallet to use for each network
- **Sync Balance:** Update wallet balances from blockchain
- **View Wallet Details:** See address, balance, and sync status

### Wallet Management:
- Each network can have multiple wallets
- One wallet per network can be set as default
- Default wallet is used for automatic transactions
- Non-default wallets can be deleted if not needed

## How Tenant-Specific Configuration Works

### Database Storage
- **Blockchain Settings:** Stored in `blockchain_settings` table (one row per tenant)
- **Blockchain Wallets:** Stored in `blockchain_wallets` table (multiple per tenant)
- **All data is tenant-scoped** automatically by the multi-tenant system

### Service Layer
The `BlockchainService` and `BlockchainExplorerService` automatically:
1. Load tenant-specific settings via `BlockchainSetting::getInstance()`
2. Use tenant's RPC URLs (or fallback to config defaults)
3. Use tenant's API keys for explorer queries
4. Use tenant's contract addresses
5. Use tenant's default wallet for signing

### Fallback Behavior
- If tenant hasn't configured an RPC URL → uses global config default
- If tenant hasn't configured an API key → uses global config default
- If tenant hasn't configured a contract → uses global config default
- This allows tenants to use system defaults or override with their own

## Environment Variables (Global Defaults)

These are **optional** global defaults that tenants can override:

```env
# Global default RPC URLs (optional, tenants can override)
ETHEREUM_RPC_URL=https://mainnet.infura.io/v3/YOUR_INFURA_API_KEY
POLYGON_RPC_URL=https://polygon-mainnet.infura.io/v3/YOUR_INFURA_API_KEY
BSC_RPC_URL=https://bsc-dataseed.binance.org
ARBITRUM_RPC_URL=https://arbitrum-mainnet.infura.io/v3/YOUR_INFURA_API_KEY
OPTIMISM_RPC_URL=https://optimism-mainnet.infura.io/v3/YOUR_INFURA_API_KEY

# Global default API keys (optional, tenants can override)
ETHERSCAN_API_KEY=your_etherscan_api_key
POLYGONSCAN_API_KEY=your_polygonscan_api_key
BSCSCAN_API_KEY=your_bscscan_api_key
ARBISCAN_API_KEY=your_arbiscan_api_key
OPTIMISTIC_ETHERSCAN_API_KEY=your_optimistic_etherscan_api_key

# Global default contract addresses (optional, tenants can override)
ETHEREUM_PROPERTY_REGISTRY_CONTRACT=0x...
POLYGON_PROPERTY_REGISTRY_CONTRACT=0x...
# etc...
```

**Note:** These are only used if tenants don't configure their own values.

## Testing Your Setup

### Test Network Connection
1. Go to Setup Wizard Step 1
2. Click "Test" button next to any RPC URL
3. System will verify connection and show current block number

### Test Wallet
1. Go to Wallets page
2. Click "Sync Balance" on a wallet
3. System will fetch current balance from blockchain

### Test Property Registration
1. Go to Blockchain Properties page
2. Click "Register Property"
3. System will attempt to register on blockchain
4. Monitor transaction status

## Troubleshooting

### "Blockchain is not set up" Error

**Solution:** Complete the setup wizard at `/admin/blockchain/setup`

### "No active default wallet found" Error

**Solution:** 
1. Go to Wallets page
2. Import a wallet for your primary network
3. Make sure it's set as default

### Connection Test Fails

**Possible causes:**
- Incorrect RPC URL
- Network connectivity issues
- RPC endpoint requires authentication
- Rate limiting

**Solutions:**
- Verify RPC URL is correct
- Try a different RPC endpoint
- Check if endpoint requires API key
- Use Infura/Alchemy for reliable endpoints

### Balance Sync Fails

**Possible causes:**
- RPC URL not configured
- Network connectivity issues
- Invalid wallet address

**Solutions:**
- Verify RPC URL is set in Step 1
- Check wallet address format (must start with 0x)
- Try syncing again later

### Transaction Fails

**Possible causes:**
- Insufficient balance for gas
- Invalid private key
- Network congestion
- Contract address mismatch

**Solutions:**
- Ensure wallet has native tokens (ETH, MATIC, etc.)
- Verify private key matches wallet address
- Increase gas price multiplier
- Check contract address is correct

## Security Best Practices

1. **Private Key Security**
   - Private keys are encrypted using Laravel's encryption
   - Never log or expose private keys
   - Use dedicated wallets for property registrations
   - Consider hardware wallets for high-value operations

2. **API Key Security**
   - API keys are stored encrypted in database
   - Use read-only API keys where possible
   - Rotate API keys periodically
   - Monitor API key usage

3. **Wallet Management**
   - Use separate wallets per network
   - Keep minimal funds in operational wallets
   - Use multi-sig wallets for production (future feature)
   - Regularly backup wallet mnemonic phrases

4. **Network Security**
   - Use private RPC endpoints in production
   - Enable webhook signature verification
   - Monitor for unauthorized transactions
   - Set up alerts for failed transactions

## Multi-Tenant Architecture

### How It Works

1. **Automatic Tenant Scoping**
   - All queries automatically filter by current tenant
   - No need to manually add tenant filters
   - Each tenant's data is completely isolated

2. **Settings Per Tenant**
   - `BlockchainSetting::getInstance()` returns tenant's settings
   - If no settings exist, creates default settings
   - Settings are automatically tenant-scoped

3. **Wallets Per Tenant**
   - Each tenant has their own wallets
   - Wallets can't be accessed by other tenants
   - Default wallet selection is per-tenant

4. **Transactions Per Tenant**
   - All blockchain property records are tenant-scoped
   - Each tenant sees only their own records
   - Statistics are calculated per-tenant

### Benefits

- **Isolation:** Complete data separation between tenants
- **Customization:** Each tenant configures their preferred networks
- **Security:** No cross-tenant data leaks
- **Flexibility:** Different tenants can use different networks

## Advanced Features

### Webhook Configuration

Set up webhooks for real-time transaction notifications:

1. Enable webhooks in Step 5
2. Set webhook URL (must be publicly accessible)
3. Set webhook secret for signature verification
4. Configure webhook provider (Alchemy, Infura, Moralis) to send events

**Webhook Payload:**
```json
{
    "event": "transaction.confirmed",
    "transaction_hash": "0x...",
    "network": "ethereum",
    "block_number": 12345678
}
```

### Gas Price Optimization

Adjust gas settings for faster or cheaper transactions:
- **Gas Price Multiplier:** Increase for faster confirmations (1.2 = 20% faster)
- **Default Gas Limit:** Set default gas limit for transactions

### Multiple Networks

Tenants can configure multiple networks:
- Set primary network for default operations
- Configure additional networks for specific use cases
- Switch between networks when registering properties

## API Endpoints

### Setup Wizard
- `GET /admin/blockchain-setup/status` - Get setup status
- `POST /admin/blockchain-setup/step-1-network` - Save network settings
- `POST /admin/blockchain-setup/step-2-explorer` - Save API keys
- `POST /admin/blockchain-setup/step-3-contracts` - Save contract addresses
- `POST /admin/blockchain-setup/step-4-wallet` - Import wallet
- `POST /admin/blockchain-setup/step-5-complete` - Complete setup
- `POST /admin/blockchain-setup/test-connection` - Test RPC connection

### Wallet Management
- `GET /admin/blockchain-wallets` - List wallets
- `POST /admin/blockchain-wallets` - Create/import wallet
- `GET /admin/blockchain-wallets/{id}` - Get wallet details
- `PUT /admin/blockchain-wallets/{id}` - Update wallet
- `DELETE /admin/blockchain-wallets/{id}` - Delete wallet
- `POST /admin/blockchain-wallets/{id}/set-default` - Set as default
- `POST /admin/blockchain-wallets/{id}/sync-balance` - Sync balance

## Migration Guide

If you have existing blockchain configuration in environment variables:

1. **Keep Environment Variables:** These become global defaults
2. **Run Migrations:** `php artisan tenants:migrate`
3. **Complete Setup Wizard:** Each tenant configures their own settings
4. **Migrate Wallets:** Import existing wallets through the wizard

The system will use tenant-specific settings when available, falling back to environment variables if not configured.

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review application logs: `storage/logs/laravel.log`
3. Verify tenant settings are configured correctly
4. Test RPC connections individually

## Next Steps

After completing setup:
1. Register your first property on blockchain
2. Monitor transaction status
3. Set up webhooks for automation
4. Configure additional wallets as needed
5. Review and optimize gas settings

Your blockchain integration is now ready for production use! 🚀
