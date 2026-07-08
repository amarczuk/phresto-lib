# JavaScript SDK

Phresto includes a small browser helper at `static/js/phresto.js` for consuming the JSON API from the frontend.

## Loading

The file is included in the default view config's `[closingjs]` section. If you build your own page, include it manually:

```html
<script src="/static/vendor/js-cookie/src/js.cookie.js"></script>
<script src="/static/js/phresto.js"></script>
```

The SDK reads the `prsid` cookie automatically and sends it as a Bearer token.

## API

All methods return a `Promise`.

### GET

```javascript
phresto.get('/product')
  .then(products => console.log(products));

phresto.getById('product', 12)
  .then(product => console.log(product));
```

### HEAD / exists

```javascript
phresto.head('product', 12)
  .then(count => console.log('count', count));

phresto.exists('product', 12)
  .then(() => console.log('exists'))
  .catch(err => console.log('not found', err.status));
```

### Create

```javascript
phresto.post('/product', {
  name: 'Widget',
  price: 9.99,
  in_stock: true
})
.then(product => console.log(product));
```

### Update

```javascript
phresto.update('product', 12, {
  price: 8.99
});

phresto.patch('/product/12', { price: 8.99 });
```

### Upsert

```javascript
phresto.upsert('/product', {
  id: 12,
  name: 'Widget v2'
});

phresto.put('/product/12', { name: 'Widget v2' });
```

### Delete

```javascript
phresto.destroy('product', 12);
phresto.delete('/product/12');
```

## Errors

Failed requests reject with a `RequestError` object:

```javascript
phresto.get('/product/999').catch(err => {
  console.log(err.status);    // HTTP status code
  console.log(err.message);   // parsed JSON response or text
});
```

## Custom token

If you store the token somewhere other than the cookie, call:

```javascript
phresto.setToken();
```

This re-reads the cookie. To use a different value, set the `Authorization` header manually with `fetch` or another client.

## Under the hood

The SDK sends JSON bodies with:

```
Content-Type: application/json
Authorization: Bearer <token_from_prsid_cookie>
```

It uses `XMLHttpRequest` and wraps it in a Promise. `DateTime` fields are returned as ISO-8601 strings from the server.
