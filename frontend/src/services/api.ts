import axios, { type AxiosInstance } from 'axios'

/**
 * Cliente HTTP central del ERP.
 * Todos los services de módulo deben usar esta instancia:
 * adjunta el token JWT y maneja la expiración de sesión de forma uniforme.
 */
const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  timeout: 30000,
  headers: { 'Content-Type': 'application/json' },
})

// Adjunta el token JWT a cada petición
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('yigm_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Sesión expirada o sin permisos → volver al login
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && window.location.pathname !== '/login') {
      sessionStorage.removeItem('yigm_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  },
)

export default api
