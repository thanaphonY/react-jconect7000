import { LockOutlined } from '@ant-design/icons'
import { App, Button, Card, Form, Input, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import { AuthBrandLayout } from '../layout/AuthBrandLayout'

type ResetState = { email?: string; resetToken?: string }

interface ResetValues {
  password: string
  password_confirmation: string
}

export function ForgotPasswordResetPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const location = useLocation()
  const state = (location.state ?? {}) as ResetState
  const email = state.email
  const resetToken = state.resetToken
  const [submitting, setSubmitting] = useState(false)

  if (!email || !resetToken) {
    return <Navigate to="/forgot_password" replace />
  }

  return (
    <AuthBrandLayout favicon="favicon.ico">
      <Card>
        <Typography.Title level={4} style={{ marginTop: 0, textAlign: 'center' }}>
          {t('set_new_password')}
        </Typography.Title>
        <Form<ResetValues>
          layout="vertical"
          requiredMark={false}
          onFinish={(values) => {
            if (values.password !== values.password_confirmation) {
              message.error(t('password_and_confirm_password_should_be_same'))
              return
            }
            setSubmitting(true)
            void apiRequest('/auth/reset-password', {
              method: 'POST',
              body: {
                email,
                password: values.password,
                password_confirmation: values.password_confirmation,
                resetToken,
              },
            })
              .then(() => {
                message.success(t('password_changed_successfully'))
                navigate('/login', { replace: true })
              })
              .catch((error: unknown) => {
                message.error(
                  error instanceof ApiError
                    ? error.message
                    : t('unable_to_update_password'),
                )
              })
              .finally(() => setSubmitting(false))
          }}
        >
          {/* PHP set_password.php: password_confirmation = New Password, password = Confirm Password */}
          <Form.Item
            label={t('NewPassword')}
            name="password_confirmation"
            rules={[{ required: true, min: 1 }]}
          >
            <Input.Password
              autoFocus
              autoComplete="new-password"
              prefix={<LockOutlined />}
              placeholder={t('NewPassword')}
            />
          </Form.Item>
          <Form.Item
            label={t('ConfirmPassword')}
            name="password"
            dependencies={['password_confirmation']}
            rules={[
              { required: true },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  if (!value || getFieldValue('password_confirmation') === value) {
                    return Promise.resolve()
                  }
                  return Promise.reject(
                    new Error(t('password_and_confirm_password_should_be_same')),
                  )
                },
              }),
            ]}
          >
            <Input.Password
              autoComplete="new-password"
              prefix={<LockOutlined />}
              placeholder={t('ConfirmPassword')}
            />
          </Form.Item>
          <Button type="primary" htmlType="submit" block loading={submitting}>
            {t('set_password')}
          </Button>
        </Form>
        <div style={{ marginTop: 12 }}>
          <Link to="/login">
            {t('please_login')} {t('here')}
          </Link>
        </div>
      </Card>
    </AuthBrandLayout>
  )
}
