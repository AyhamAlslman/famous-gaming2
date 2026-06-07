# FAMOUS GAMING Node.js MVC App

This project is now the Node.js and PostgreSQL version of the FAMOUS GAMING booking app.

## Structure

```text
famous-gaming2/
├── assets/                # CSS and browser JavaScript
├── images/                # Static images and icons
├── uploads/               # Uploaded room, store, and profile media
├── sql/
│   └── postgres/
│       └── schema.sql     # Full PostgreSQL schema
├── scripts/
│   └── setup-postgres.js  # Creates PostgreSQL tables/views/triggers
├── src/
│   ├── app.js             # Express app setup
│   ├── server.js          # Node entry point
│   ├── config/            # Environment and PostgreSQL connection
│   ├── controllers/       # MVC controllers
│   ├── middleware/        # Auth and view locals
│   ├── models/            # PostgreSQL model queries
│   ├── routes/            # Clean Node route map
│   └── utils/             # Formatting and token helpers
├── views/
│   ├── layouts/           # Main EJS layout
│   ├── partials/          # Header/footer/messages
│   ├── public/            # Home, services, about, contact, menu
│   ├── auth/              # Login/register/password pages
│   ├── booking/           # Booking, payment, My Bookings
│   ├── store/             # Store and checkout
│   ├── complaints/        # Support tickets
│   └── admin/             # Admin dashboard and lists
├── .env.example           # Copy to .env and configure
└── package.json           # Node dependencies and scripts
```

## Setup

1. Install PostgreSQL and create an empty database, for example `playroom_node`.
2. Copy `.env.example` to `.env`.
3. Edit `.env` with your PostgreSQL settings.
4. Install dependencies:

```bash
npm install
```

5. Create all PostgreSQL tables:

```bash
npm run db:setup
```

6. Start the Node app:

```bash
npm run dev
```

Open `http://localhost:3000`.

## Main Routes

- `/` home
- `/services`, `/services/gaming`, `/services/hospitality`, `/services/events`
- `/menu`, `/store`, `/store/checkout`
- `/login`, `/register`, `/logout`
- `/dashboard`, `/booking`, `/my-bookings`, `/payment`
- `/complaints`, `/support-chatbot`
- `/admin`, `/admin/dashboard`, `/admin/bookings`, `/admin/rooms`, `/admin/store-products`, `/admin/store-orders`

## Checks

```bash
npm run check
```
