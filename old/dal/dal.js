import "server-only"

import {cookies} from "next/headers"
import {decrypt} from "@/app/lib/sessions"
import {cache} from "react"
import {redirect} from "next/navigation";

export const verifySession = cache(async ()=>{
  const cookie = (await cookies()).get('session')?.value
  const session = await decrypt(cookie)

  if (!seesion.token){
    redirect('/login')
  }

  return {
    isAuth: true,
    token: session.token
  }
})

export const getUser = async ()=>{
  const session = await verifySession()

  if (!session.token) return null

  try {

    const data = await api.get('/profile')

    console.log('data', data)

  } catch (error) {

  }


}