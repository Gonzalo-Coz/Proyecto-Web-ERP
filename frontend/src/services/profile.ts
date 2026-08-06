import api from '@/services/api'
import type { ChangePasswordPayload, Profile, ProfilePayload } from '@/types/profile'

/** Cliente API del autoservicio de perfil (/api/v1/profile). */
export const profileService = {
  get(): Promise<Profile> {
    return api.get('/profile').then((r) => r.data)
  },
  update(payload: ProfilePayload): Promise<Profile> {
    return api.patch('/profile', payload).then((r) => r.data)
  },
  changePassword(payload: ChangePasswordPayload): Promise<void> {
    return api.patch('/profile/password', payload).then(() => undefined)
  },
  uploadAvatar(file: File): Promise<Profile> {
    const form = new FormData()
    form.append('avatar', file)
    return api
      .post('/profile/avatar', form, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((r) => r.data)
  },
  removeAvatar(): Promise<Profile> {
    return api.delete('/profile/avatar').then((r) => r.data)
  },
}
