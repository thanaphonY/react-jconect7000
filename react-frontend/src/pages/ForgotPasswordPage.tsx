import { MailOutlined } from '@ant-design/icons'
import { App, Button, Card, Form, Input, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import { AuthBrandLayout } from '../layout/AuthBrandLayout'

interface ForgotValues {
  email: string
}

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const [submitting, setSubmitting] = useState(false)

  return (
    <AuthBrandLayout favicon="favicon.ico">
      <Card>
        <Typography.Title level={4} style={{ marginTop: 0 }}>
          {t('forgot_password')}
        </Typography.Title>
        <Typography.Paragraph type="secondary">{t('forget_pass_msg')}</Typography.Paragraph>
        <Form<ForgotValues>
          layout="vertical"
          requiredMark={false}
          onFinish={(values) => {
            setSubmitting(true)
            void apiRequest<{ ok: boolean; email: string; maskedEmail: string }>(
              '/auth/forgot-password',
              { method: 'POST', body: { email: values.email } },
            )
              .then((data) => {
                message.success(t('otp_sent_successfully'))
                navigate('/forgot_password/otp', {
                  replace: true,
                  state: { email: data.email, maskedEmail: data.maskedEmail },
                })
              })
              .catch((error: unknown) => {
                if (error instanceof ApiError) {
                  if (error.code === 'ACCOUNT_NOT_FOUND') {
                    message.error(t('this_account_does_not_exist'))
                  } else if (error.code === 'RATE_LIMITED') {
                    message.error(t('please_wait_before_resend'))
                  } else if (error.code === 'EMAIL_FAILED') {
                    message.error(t('failed_to_send_email'))
                  } else {
                    message.error(error.message)
                  }
                } else {
                  message.error(t('failed_to_send_email'))
                }
              })
              .finally(() => setSubmitting(false))
          }}
        >
          <Form.Item
            label={t('email')}
            name="email"
            rules={[{ required: true, type: 'email' }]}
          >
            <Input
              autoFocus
              autoComplete="email"
              prefix={<MailOutlined />}
              placeholder={t('email')}
            />
          </Form.Item>
          <Button type="primary" htmlType="submit" block loading={submitting}>
            {t('get_new_password')}
          </Button>
        </Form>
        <div style={{ marginTop: 12, textAlign: 'center' }}>
          <Link to="/login" title={t('back')} style={{ fontSize: 22, lineHeight: 1 }}>
            <i className="bi bi-arrow-left-circle" />
          </Link>
        </div>
      </Card>
    </AuthBrandLayout>
  )
}
