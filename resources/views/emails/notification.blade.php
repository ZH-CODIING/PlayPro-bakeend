<!DOCTYPE html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <title>{{ $subjectText ?? 'إشعار جديد' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap"
      rel="stylesheet"
    />
    <style>
      body {
        margin: 0;
        padding: 0;
        font-family: "Tajawal", sans-serif;
        background-color: #f7f9fc;
        direction: rtl;
        text-align: right;
      }

      .email-container {
        max-width: 600px;
        margin: 40px auto;
        background-color: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
      }

      .email-header {
        background: linear-gradient(90deg, #f1e1ff, #f2d5ff);
        padding: 10px 24px;
        text-align: center;
      }

      .email-header img {
        max-width: 150px;
        height: auto;
      }

      .email-body {
        padding: 30px;
        color: #333333;
      }

      .email-body h1 {
        font-size: 26px;
        color: #6a1b7f;
        margin-bottom: 20px;
        font-weight: 700;
      }

      .email-body p {
        font-size: 16px;
        line-height: 1.8;
        margin: 0 0 15px;
        color: #555555;
      }

      .email-footer {
        text-align: center;
        font-size: 13px;
        color: white;
        padding: 20px;
        background-color: #490041;
        border-top: 1px solid #e0e4e8;
      }

      @media (max-width: 600px) {
        .email-container {
          margin: 20px auto;
          border-radius: 0;
        }

        .email-body {
          padding: 20px;
        }

        .email-body h1 {
          font-size: 22px;
        }

        .email-body p {
          font-size: 15px;
        }
      }
    </style>
  </head>

  <body>
    <div class="email-container" style="direction: rtl">
      <div class="email-header">
        <img
          src="https://playpro.abdaealmasi.store/storage/images/logo.png"
          alt="شعار PlayPro"
        />
      </div>
      <div class="email-body" style="direction: rtl">
        <h1 style="direction: rtl; text-align: right">{{ $subjectText }}</h1>
        <p style="direction: rtl; text-align: right">
          {!! nl2br(e($bodyText)) !!}
        </p>
      </div>
      <div class="email-footer">
        <p>© {{ date('Y') }} PlayPro – جميع الحقوق محفوظة.</p>
      </div>
    </div>
  </body>
</html>