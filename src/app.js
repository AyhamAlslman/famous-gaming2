const path = require('path');
const express = require('express');
const expressLayouts = require('express-ejs-layouts');
const session = require('express-session');
const flash = require('connect-flash');
const methodOverride = require('method-override');
const morgan = require('morgan');
const env = require('./config/env');
const viewLocals = require('./middleware/viewLocals');
const routes = require('./routes');

const app = express();

app.set('view engine', 'ejs');
app.set('views', path.join(process.cwd(), 'views'));
app.use(expressLayouts);
app.set('layout', 'layouts/main');

app.use(morgan(env.nodeEnv === 'production' ? 'combined' : 'dev'));
app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(methodOverride('_method'));
app.use(session({
  secret: env.sessionSecret,
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: env.nodeEnv === 'production'
  }
}));
app.use(flash());

app.use('/assets', express.static(path.join(process.cwd(), 'assets')));
app.use('/images', express.static(path.join(process.cwd(), 'images')));
app.use('/uploads', express.static(path.join(process.cwd(), 'uploads')));

app.use(viewLocals);
app.use(routes);

app.use((req, res) => {
  res.status(404).render('public/not-found', { title: 'Page not found' });
});

app.use((error, req, res, next) => {
  console.error(error);
  res.status(500).render('public/error', {
    title: 'Server error',
    error: env.nodeEnv === 'production' ? null : error
  });
});

module.exports = app;
