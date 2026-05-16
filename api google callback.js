import mysql from 'mysql2/promise';

export default async function handler(req, res) {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  
  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });

  const { credential } = req.body;
  
  if (!credential) {
    return res.redirect(302, '/login.html?error=no_credential');
  }

  // Decode JWT
  function decodeJWT(token) {
    const parts = token.split('.');
    if (parts.length !== 3) return null;
    const payload = Buffer.from(parts[1], 'base64').toString();
    return JSON.parse(payload);
  }

  const payload = decodeJWT(credential);
  
  if (!payload || !payload.email) {
    return res.redirect(302, '/login.html?error=invalid_token');
  }

  const email = payload.email.toLowerCase().trim();
  const name = payload.name || '';
  const picture = payload.picture || '';

  // Validate domain
  if (!email.endsWith('@247ga.co')) {
    return res.redirect(302, '/login.html?error=invalid_domain');
  }

  // Connect to DB
  const conn = await mysql.createConnection({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME
  });

  // Check user
  const [rows] = await conn.execute(
    'SELECT id, email, name, is_active, picture FROM users WHERE email = ?',
    [email]
  );

  let userId;

  if (rows.length > 0) {
    const user = rows[0];
    
    if (!user.is_active) {
      await conn.end();
      return res.redirect(302, '/login.html?error=account_deactivated');
    }

    // Update user
    await conn.execute(
      'UPDATE users SET name = ?, picture = ?, auth_provider = "google", last_login = NOW() WHERE id = ?',
      [name, picture, user.id]
    );
    
    userId = user.id;
  } else {
    // Insert new user
    const [result] = await conn.execute(
      'INSERT INTO users (email, name, picture, auth_provider) VALUES (?, ?, ?, "google")',
      [email, name, picture]
    );
    userId = result.insertId;
  }

  await conn.end();

  // Set session cookie (or JWT token)
  res.setHeader('Set-Cookie', [
    `user_id=${userId}; Path=/; HttpOnly; Secure; SameSite=Strict`,
    `user_email=${email}; Path=/; Secure; SameSite=Strict`,
    `user_name=${encodeURIComponent(name)}; Path=/; Secure; SameSite=Strict`
  ]);

  return res.redirect(302, '/index.html');
}