import { KeyOutlined } from '@ant-design/icons'
import { App, Button, Card, Form, Input, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import { AuthBrandLayout } from '../layout/AuthBrandLayout'

type OtpState = { email?: string; maskedEmail?: string }

interface OtpValues {
  otp: string
}

export function ForgotPasswordOtpPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const location = useLocation()
  const state = (location.state ?? {}) as OtpState
  const email = state.email
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)

  if (!email) {
    return <Navigate to="/forgot_password" replace />
  }

  const masked = state.maskedEmail || email
  const hint = t('enter_otp_msg').replace('%s', masked)

  return (
    <AuthBrandLayout favicon="favicon.ico">
      <Card>
        <Typography.Title level={4} style={{ marginTop: 0 }}>
          {t('otp')}
        </Typography.Title>
        <Typography.Paragraph type="secondary">{hint}</Typography.Paragraph>
        <Form<OtpValues>
          layout="vertical"
          requiredMark={false}
          onFinish={(values) => {
            setSubmitting(true)
            void apiRequest<{
              ok: boolean
              verified: boolean
              email: string
              resetToken: string
            }>('/auth/verify-otp', {
              method: 'POST',
              body: { email, otp: values.otp },
            })
              .then((data) => {
                message.success(t('otp_verified_set_password'))
                navigate('/forgot_password/reset', {
                  replace: true,
                  state: { email: data.email, resetToken: data.resetToken },
                })
              })
              .catch((error: unknown) => {
                if (error instanceof ApiError) {
                  if (error.code === 'INVALID_OTP') message.error(t('invalid_otp'))
                  else if (error.code === 'OTP_EXPIRED') message.error(t('otp_expired'))
                  else if (error.code === 'TOO_MANY_ATTEMPTS')
                    message.error(t('otp_too_many_attempts'))
                  else message.error(error.message)
                } else {
                  message.error(t('invalid_otp'))
                }
              })
              .finally(() => setSubmitting(false))
          }}
        >
          <Form.Item
            label={t('otp')}
            name="otp"
            rules={[
              { required: true },
              { len: 6, message: t('otp') },
              { pattern: /^\d{6}$/, message: t('otp') },
            ]}
          >
            <Input
              autoFocus
              maxLength={6}
              inputMode="numeric"
              placeholder="000000"
              prefix={<KeyOutlined />}
            />
          </Form.Item>
          <Space direction="vertical" style={{ width: '100%' }} size={8}>
            <Button type="primary" htmlType="submit" block loading={submitting}>
              {t('verify_otp')}
            </Button>
            <Button
              block
              loading={resending}
              onClick={() => {
                setResending(true)
                void apiRequest('/auth/resend-otp', {
                  method: 'POST',
                  body: { email },
                })
                  .then(() => message.success(t('otp_sent_successfully')))
                  .catch((error: unknown) => {
                    if (error instanceof ApiError && error.code === 'RATE_LIMITED') {
                      message.error(t('please_wait_before_resend'))
                    } else {
                      message.error(
                        error instanceof Error ? error.message : t('failed_to_send_email'),
                      )
                    }
                  })
                  .finally(() => setResending(false))
              }}
            >
              {t('resend_otp')}
            </Button>
          </Space>
        </Form>
        <div style={{ marginTop: 12, textAlign: 'center' }}>
          <Link to="/forgot_password" title={t('back')} style={{ fontSize: 22, lineHeight: 1 }}>
            <i className="bi bi-arrow-left-circle" />
          </Link>
        </div>
      </Card>
    </AuthBrandLayout>
  )
}
