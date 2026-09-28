# Database Schema & Entity Relationships
## Project: Trading Journal & Performance Monitoring Dashboard

---

## 1. Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    USERS ||--o{ ACCOUNTS : owns
    ACCOUNTS ||--o{ TRADES : contains
    USERS ||--o{ STRATEGIES : defines
    USERS ||--o{ TAGS : creates
    STRATEGIES ||--o{ TRADES : categorizes
    TRADES ||--o{ TRADE_ATTACHMENTS : has
    TRADES }o--o{ TAGS : labeled_with

    USERS {
        bigint id PK
        string name
        string email
        string password
        string currency_preference
        timestamps created_at
    }

    ACCOUNTS {
        bigint id PK
        bigint user_id FK
        string name
        string broker
        string account_type "Forex | Crypto | Stock | Futures"
        string currency "USD | IDR | EUR"
        decimal initial_balance
        decimal current_balance
        boolean is_active
        timestamps created_at
    }

    STRATEGIES {
        bigint id PK
        bigint user_id FK
        string name
        text description
        string color_code
        timestamps created_at
    }

    TAGS {
        bigint id PK
        bigint user_id FK
        string name
        string category "Emotion | MarketCondition | Mistake | Session"
        string color_code
        timestamps created_at
    }

    TRADES {
        bigint id PK
        bigint account_id FK
        bigint strategy_id FK "Nullable"
        string symbol "e.g. BTCUSDT, EURUSD"
        enum direction "long | short"
        enum status "open | closed | cancelled"
        datetime entry_at
        decimal entry_price
        datetime exit_at "Nullable"
        decimal exit_price "Nullable"
        decimal quantity
        decimal stop_loss "Nullable"
        decimal take_profit "Nullable"
        decimal fees "Default 0"
        decimal gross_pnl "Nullable"
        decimal net_pnl "Nullable"
        decimal roi_percentage "Nullable"
        decimal planned_rr "Nullable"
        decimal actual_rr "Nullable"
        text notes "Nullable"
        text lesson_learned "Nullable"
        timestamps created_at
    }

    TRADE_ATTACHMENTS {
        bigint id PK
        bigint trade_id FK
        string file_path
        enum stage "before_entry | after_exit | context"
        string caption "Nullable"
        timestamps created_at
    }

    TRADE_TAGS {
        bigint trade_id FK
        bigint tag_id FK
    }
```

---

## 2. Table Specifications & Indexes

### `accounts` Table
- `id` (BigIncrements, PK)
- `user_id` (ForeignId -> users.id, Cascade On Delete)
- `name` (String, e.g. "Main Binance Futures")
- `broker` (String nullable, e.g. "Binance", "FTMO", "ICMarkets")
- `account_type` (String, default: "forex")
- `currency` (String, default: "USD")
- `initial_balance` (Decimal 15,2)
- `current_balance` (Decimal 15,2)
- `is_active` (Boolean, default: true)
- `timestamps`

### `strategies` Table
- `id` (BigIncrements, PK)
- `user_id` (ForeignId -> users.id, Cascade On Delete)
- `name` (String, e.g. "Breakout Pullback")
- `description` (Text nullable)
- `color_code` (String nullable, default: "#3B82F6")
- `timestamps`

### `tags` Table
- `id` (BigIncrements, PK)
- `user_id` (ForeignId -> users.id, Cascade On Delete)
- `name` (String, e.g. "FOMO", "Disciplined", "London Session")
- `category` (Enum/String: 'emotion', 'mistake', 'market_condition', 'session')
- `color_code` (String nullable)
- `timestamps`

### `trades` Table
- `id` (BigIncrements, PK)
- `account_id` (ForeignId -> accounts.id, Cascade On Delete, Index)
- `strategy_id` (ForeignId -> strategies.id, Nullable, Null On Delete, Index)
- `symbol` (String, Index, e.g. "XAUUSD")
- `direction` (Enum: 'long', 'short')
- `status` (Enum: 'open', 'closed', 'cancelled')
- `entry_at` (DateTime, Index)
- `entry_price` (Decimal 18,8)
- `exit_at` (DateTime nullable, Index)
- `exit_price` (Decimal 18,8 nullable)
- `quantity` (Decimal 18,8)
- `stop_loss` (Decimal 18,8 nullable)
- `take_profit` (Decimal 18,8 nullable)
- `fees` (Decimal 15,2, default: 0)
- `gross_pnl` (Decimal 15,2 nullable)
- `net_pnl` (Decimal 15,2 nullable, Index)
- `roi_percentage` (Decimal 8,4 nullable)
- `planned_rr` (Decimal 8,2 nullable)
- `actual_rr` (Decimal 8,2 nullable)
- `notes` (Text nullable)
- `lesson_learned` (Text nullable)
- `timestamps`

### `trade_attachments` Table
- `id` (BigIncrements, PK)
- `trade_id` (ForeignId -> trades.id, Cascade On Delete, Index)
- `file_path` (String)
- `stage` (Enum: 'before_entry', 'after_exit', 'general')
- `caption` (String nullable)
- `timestamps`

### `trade_tag` (Pivot Table)
- `trade_id` (ForeignId -> trades.id, Cascade On Delete)
- `tag_id` (ForeignId -> tags.id, Cascade On Delete)
- Primary Key: `(trade_id, tag_id)`
