const nodemailer = require('nodemailer');

// Load environment variables for local development
if (process.env.NODE_ENV !== 'production') {
  require('dotenv').config();
}

export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  try {
    // Debug environment variables
    console.log('Email config test:', {
      host: process.env.EMAIL_HOST,
      port: process.env.EMAIL_PORT,
      user: process.env.EMAIL_USER ? '***@' + process.env.EMAIL_USER.split('@')[1] : 'undefined',
      pass: process.env.EMAIL_PASS ? 'configured' : 'missing'
    });

    const transporter = nodemailer.createTransporter({
      host: process.env.EMAIL_HOST || 'smtp.gmail.com',
      port: parseInt(process.env.EMAIL_PORT) || 587,
      secure: false, // Use STARTTLS
      auth: {
        user: process.env.EMAIL_USER,
        pass: process.env.EMAIL_PASS,
      },
      tls: {
        rejectUnauthorized: false
      },
      connectionTimeout: 60000,
      greetingTimeout: 30000,
      socketTimeout: 60000
    });

    // Verify connection
    await transporter.verify();
    console.log('✅ Email connection verified successfully');
    
    res.json({ 
      success: true, 
      message: 'Email connection verified successfully',
      config: {
        host: process.env.EMAIL_HOST,
        port: process.env.EMAIL_PORT,
        user: process.env.EMAIL_USER ? '***@' + process.env.EMAIL_USER.split('@')[1] : 'undefined'
      }
    });

  } catch (error) {
    console.error('❌ Email connection failed:', error);
    res.status(500).json({ 
      error: 'Email connection failed', 
      details: error.message,
      code: error.code
    });
  }
}