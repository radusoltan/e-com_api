"use client"

import {useActionState, useState} from "react"
import {login} from "@/app/(auth)/action";

function classNames(...classes) {
  return classes.filter(Boolean).join(' ')
}

const LoginPage = ()=>{

  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [state, action, pending] = useActionState(login,{username, password})

  console.log(state)

  return <div className="p-6 space-y-4 md:space-y-6 sm:p-8">
    <h1 className="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
      Sign in to your account
    </h1>
    <form className="space-y-4 md:space-y-6" action={action}>
      {state?.errors?.message &&
        <p className="mt-2 text-sm text-red-600 dark:text-red-500"><span className="font-medium">Oops!</span>{state.errors.message}</p>}
      <div>
        <label htmlFor="username" className={classNames(
          state?.errors?.username && "block mb-2 text-sm font-medium text-red-700 dark:text-red-500",
          "block mb-2 text-sm font-medium text-gray-900 dark:text-white"
        )}>User Name</label>
        <input type="text" name="username"
               className={classNames(
                 state?.errors?.username && "bg-red-50 border border-red-500 text-red-900 placeholder-red-700 text-sm rounded-lg focus:ring-red-500 dark:bg-gray-700 focus:border-red-500 block w-full p-2.5 dark:text-red-500 dark:placeholder-red-500 dark:border-red-500",
                 "bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
               )}
               placeholder="username"/>
        {state?.errors?.username &&
          <p className="mt-2 text-sm text-red-600 dark:text-red-500"><span className="font-medium">{state.errors.username}</span></p>}
      </div>
      <div>
        <label htmlFor="password"
               className={classNames(
                 state?.errors?.username && "block mb-2 text-sm font-medium text-red-700 dark:text-red-500",
                 "block mb-2 text-sm font-medium text-gray-900 dark:text-white"
               )}>Password</label>
        <input type="password" name="password" id="password" placeholder="••••••••"
               className={classNames(
                 state?.errors?.username && "bg-red-50 border border-red-500 text-red-900 placeholder-red-700 text-sm rounded-lg focus:ring-red-500 dark:bg-gray-700 focus:border-red-500 block w-full p-2.5 dark:text-red-500 dark:placeholder-red-500 dark:border-red-500",
                 "bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
               )}
               required=""/>
        {state?.errors?.password &&
          <p className="mt-2 text-sm text-red-600 dark:text-red-500"><span className="font-medium">{state.errors.password}</span></p>}
      </div>
      <div className="flex items-center justify-between">
        <div className="flex items-start">
          <div className="flex items-center h-5">
            <input id="remember" aria-describedby="remember" type="checkbox"
                   className="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-blue-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-blue-600 dark:ring-offset-gray-800"
                   required=""/>
          </div>
          <div className="ml-3 text-sm">
            <label htmlFor="remember" className="text-gray-500 dark:text-gray-300">Remember me</label>
          </div>
        </div>
        <a href="#" className="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500">Forgot
          password?</a>
      </div>
      <button type="submit"
              className="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Sign
        in
      </button>
      <p className="text-sm font-light text-gray-500 dark:text-gray-400">
        Don’t have an account yet? <a href="#"
                                      className="font-medium text-blue-600 hover:underline dark:text-blue-500">Sign
        up</a>
      </p>
    </form>
  </div>
}
export default LoginPage