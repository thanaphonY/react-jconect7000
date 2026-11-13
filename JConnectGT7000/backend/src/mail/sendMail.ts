import nodemailer from 'nodemailer';

export interface MailSettings {
  mail_setting?: string;
  company_name?: string;
  SMTP_EMAIL?: string;
  HOST?: string;
  PORT?: string;
  SMTP_SECURE?: string;
  SMTP_PASSWORD?: string;
  EMAIL?: string;
  website?: string;
}

export interface SentMail {
  to: string;
  subject: string;
  html: string;
}

const sentMails: SentMail[] = [];

export function drainSentMails(): SentMail[] {
  const copy = [...sentMails];
  sentMails.length = 0;
  return copy;
}

export function peekSentMails(): SentMail[] {
  return [...sentMails];
}

/** When true, never opens a real SMTP connection (Vitest sets this). */
let forceDryRun = false;

export function setMailDryRun(value: boolean): void {
  forceDryRun = value;
}

export async function sendHtmlMail(
  settings: MailSettings,
  to: string,
  subject: string,
  html: string,
): Promise<boolean> {
  sentMails.push({ to, subject, html });
  if (forceDryRun || process.env.MAIL_DRY_RUN === '1' || process.env.NODE_ENV === 'test') {
    return true;
  }

  try {
    if (settings.mail_setting === 'php_mailer') {
      const port = Number(settings.PORT) || 587;
      const secureFlag = String(settings.SMTP_SECURE ?? '').toUpperCase();
      const transporter = nodemailer.createTransport({
        host: settings.HOST || 'localhost',
        port,
        secure: secureFlag === 'SSL' && port === 465,
        auth: {
          user: settings.SMTP_EMAIL || '',
          pass: settings.SMTP_PASSWORD || '',
        },
      });
      await transporter.sendMail({
        from: settings.SMTP_EMAIL || settings.EMAIL || settings.company_name || 'noreply@localhost',
        to,
        subject,
        html,
      });
      return true;
    }

    const transporter = nodemailer.createTransport({
      sendmail: true,
      newline: 'unix',
      path: '/usr/sbin/sendmail',
    });
    await transporter.sendMail({
      from: settings.EMAIL || settings.SMTP_EMAIL || 'noreply@localhost',
      to,
      subject,
      html,
    });
    return true;
  } catch {
    return false;
  }
}

export function renderTemplate(html: string, vars: Record<string, string | number>): string {
  let body = html;
  for (const [key, value] of Object.entries(vars)) {
    body = body.split(`{var_${key}}`).join(String(value));
  }
  return body;
}

export const DEFAULT_OTP_EMAIL_BODY = `<p>Hi {var_user_name},</p>
<p>Your OTP to reset the password for {var_website_name} is:</p>
<p style="font-size:28px;letter-spacing:6px;font-weight:bold;">{var_otp}</p>
<p>This code expires in {var_otp_expire_minutes} minutes.</p>
<p>If you did not request a password reset, please ignore this email.</p>`;

export const DEFAULT_INVITE_EMAIL_BODY = `<p>You have been invited to register.</p>
<p>Email: {var_user_email}</p>
<p>{var_inviation_link}</p>`;
