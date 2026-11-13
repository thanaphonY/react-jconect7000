export type SessionUser = {
  id: number
  name: string
  email: string
  userType: string
  profilePic: string | null
}

export type MenuItem = {
  labelKey: string
  path: string
}

export type Session = {
  accessToken: string
  user: SessionUser
  menu: MenuItem[]
  website: string
}
