import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, Card, Col, Form, Input, Row, Space, Typography, Upload } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, apiFormRequest, apiRequest } from '../api/client'
import { assetImageUrl } from '../lib/assets'

type Profile = {
  id: number
  name: string
  email: string
  userType: string
  status: string | null
  profilePic: string | null
}

type ProfileForm = {
  name: string
  email: string
  currentpassword?: string
  password?: string
  confirmPassword?: string
}

export function ProfilePage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const [form] = Form.useForm<ProfileForm>()
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)

  const profile = useQuery({
    queryKey: ['profile'],
    queryFn: () => apiRequest<Profile>('/profile'),
  })

  useEffect(() => {
    if (profile.data) {
      form.setFieldsValue({
        name: profile.data.name,
        email: profile.data.email,
        currentpassword: '',
        password: '',
        confirmPassword: '',
      })
      setPreview(assetImageUrl(profile.data.profilePic, 'user.png'))
    }
  }, [profile.data, form])

  const save = useMutation({
    mutationFn: async (values: ProfileForm) => {
      const fd = new FormData()
      fd.append('name', values.name)
      fd.append('email', values.email)
      if (values.currentpassword) fd.append('currentpassword', values.currentpassword)
      if (values.password) fd.append('password', values.password)
      if (values.confirmPassword) fd.append('confirmPassword', values.confirmPassword)
      fd.append('fileOld', profile.data?.profilePic || 'user.png')
      if (file) fd.append('profile_pic', file)
      return apiFormRequest<Profile>('/profile', fd, { method: 'PUT' })
    },
    onSuccess: async () => {
      message.success(t('your_data_updated_successfully'))
      setFile(null)
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
    },
    onError: (error: unknown) => {
      if (error instanceof ApiError) {
        if (error.code === 'INVALID_CURRENT_PASSWORD') {
          message.error(t('enter_valid_current_password'))
        } else if (error.code === 'CURRENT_PASSWORD_REQUIRED') {
          message.error(t('current_password_is_required'))
        } else if (error.code === 'PASSWORD_MISMATCH') {
          message.error(t('password_and_confirm_password_should_be_same'))
        } else if (error.code === 'EMAIL_EXISTS') {
          message.error(t('this_email_already_registered_with_us'))
        } else {
          message.error(error.message)
        }
      } else {
        message.error(t('unable_to_update_password'))
      }
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div>
        <Typography.Title level={3} style={{ marginBottom: 4 }}>
          <i className="bi bi-person-circle" style={{ marginRight: 8 }} />
          {t('profile')}
        </Typography.Title>
        <Typography.Text type="secondary">{t('manage_your_profile_information')}</Typography.Text>
      </div>

      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => {
          if (values.password && values.password !== values.confirmPassword) {
            message.error(t('password_and_confirm_password_should_be_same'))
            return
          }
          save.mutate(values)
        }}
      >
        <Row gutter={16}>
          <Col xs={24} lg={8}>
            <Card
              title={
                <span>
                  <i className="bi bi-camera" style={{ marginRight: 8 }} />
                  {t('profile_picture')}
                </span>
              }
            >
              <div style={{ textAlign: 'center' }}>
                <div
                  style={{
                    position: 'relative',
                    width: 140,
                    height: 140,
                    margin: '0 auto 16px',
                    borderRadius: '50%',
                    overflow: 'hidden',
                    display: 'inline-block',
                  }}
                  className="profile-picture-container"
                >
                  <img
                    src={preview || assetImageUrl('user.png')}
                    alt="profile"
                    style={{
                      width: 140,
                      height: 140,
                      borderRadius: '50%',
                      objectFit: 'cover',
                      display: 'block',
                    }}
                  />
                  <div
                    style={{
                      position: 'absolute',
                      inset: 0,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      background: 'rgba(0,0,0,0.35)',
                      color: '#fff',
                      fontSize: 28,
                      opacity: 0.85,
                      pointerEvents: 'none',
                    }}
                  >
                    <i className="bi bi-camera-fill" />
                  </div>
                </div>
                <Upload
                  accept="image/*"
                  showUploadList={false}
                  beforeUpload={(uploadFile) => {
                    setFile(uploadFile)
                    setPreview(URL.createObjectURL(uploadFile))
                    return false
                  }}
                >
                  <Button block icon={<i className="bi bi-upload" />}>
                    {t('change_picture')}
                  </Button>
                </Upload>
                <Typography.Paragraph type="secondary" style={{ marginTop: 12, marginBottom: 0 }}>
                  <i className="bi bi-info-circle" style={{ marginRight: 4 }} />
                  {t('supported_formats')}: JPG, PNG, GIF
                </Typography.Paragraph>
              </div>
            </Card>
          </Col>
          <Col xs={24} lg={16}>
            <Card
              title={
                <span>
                  <i className="bi bi-person-badge" style={{ marginRight: 8 }} />
                  {t('personal_information')}
                </span>
              }
            >
              <Row gutter={16}>
                <Col xs={24} md={12}>
                  <Form.Item
                    label={
                      <span>
                        <i className="bi bi-person" style={{ marginRight: 4 }} />
                        {t('name')}
                      </span>
                    }
                    name="name"
                    rules={[{ required: true }]}
                  >
                    <Input prefix={<i className="bi bi-person" />} />
                  </Form.Item>
                </Col>
                <Col xs={24} md={12}>
                  <Form.Item
                    label={
                      <span>
                        <i className="bi bi-envelope" style={{ marginRight: 4 }} />
                        {t('email')}
                      </span>
                    }
                    name="email"
                    rules={[{ required: true, type: 'email' }]}
                  >
                    <Input prefix={<i className="bi bi-envelope" />} />
                  </Form.Item>
                </Col>
                <Col xs={24} md={12}>
                  <Form.Item label={t('user_type')}>
                    <Input
                      value={profile.data?.userType ?? ''}
                      readOnly
                      disabled
                      prefix={<i className="bi bi-shield" />}
                    />
                  </Form.Item>
                </Col>
                <Col xs={24} md={12}>
                  <Form.Item label={t('status')}>
                    <Input
                      value={profile.data?.status ?? ''}
                      readOnly
                      disabled
                      prefix={<i className="bi bi-circle-fill" />}
                    />
                  </Form.Item>
                </Col>
              </Row>

              <Typography.Title level={5}>
                <i className="bi bi-lock" style={{ marginRight: 8 }} />
                {t('ChangePassword')}
              </Typography.Title>
              <Form.Item label={t('current_password')} name="currentpassword">
                <Input.Password prefix={<i className="bi bi-key" />} />
              </Form.Item>
              <Row gutter={16}>
                <Col xs={24} md={12}>
                  <Form.Item label={t('NewPassword')} name="password">
                    <Input.Password prefix={<i className="bi bi-lock" />} />
                  </Form.Item>
                </Col>
                <Col xs={24} md={12}>
                  <Form.Item
                    label={t('confirm_new_password')}
                    name="confirmPassword"
                    dependencies={['password']}
                    rules={[
                      ({ getFieldValue }) => ({
                        validator(_, value) {
                          if (!value || getFieldValue('password') === value) {
                            return Promise.resolve()
                          }
                          return Promise.reject(
                            new Error(t('password_and_confirm_password_should_be_same')),
                          )
                        },
                      }),
                    ]}
                  >
                    <Input.Password prefix={<i className="bi bi-lock-fill" />} />
                  </Form.Item>
                </Col>
              </Row>
              <Button
                type="primary"
                htmlType="submit"
                loading={save.isPending}
                icon={<i className="bi bi-check-circle" />}
              >
                {t('save')}
              </Button>
            </Card>
          </Col>
        </Row>
      </Form>
    </Space>
  )
}
