import '@ant-design/v5-patch-for-react-19'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './i18n'
import './index.css'
import './lib/dayjs'
import 'antd/dist/reset.css'
import { applyInitialColorMode } from './theme/colorMode'

applyInitialColorMode()

const bi = document.createElement('link')
bi.rel = 'stylesheet'
bi.href = '/bootstrap-icons/bootstrap-icons.min.css'
document.head.appendChild(bi)

const fa = document.createElement('link')
fa.rel = 'stylesheet'
fa.href = '/fontawesome/css/all.min.css'
document.head.appendChild(fa)

const queryClient = new QueryClient()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
    </QueryClientProvider>
  </StrictMode>,
)
