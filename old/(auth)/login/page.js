"use client"

import {useActionState} from "react"
import {login} from "@/app/actions/auth"
import Link from "next/link";

function classNames(...classes) {
  return classes.filter(Boolean).join(' ')
}

const Login = () => {

  const [state, action, pending] = useActionState(login, undefined)

  return (
    <div className="p-6 space-y-4 md:space-y-6 sm:p-8">
      <h1 className="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
        Sign in to your account
      </h1>
      {state?.errors && <p className="mt-2 text-sm text-red-600 dark:text-red-500">{state.message}</p>}
      <form className="space-y-4 md:space-y-6" action={action}>
        <div>
          <label htmlFor="email" className={classNames(
            state?.errors?.username ? 'text-red-600 dark:text-red-500' : 'text-gray-900 dark:text-white',
            "block mb-2 text-sm font-medium"
          )}>Username</label>
          <input type="text" name="username"
                 className={classNames(
                   state?.errors?.username ?
                     'bg-red-50 border border-red-500 text-red-900 placeholder-red-700 text-sm rounded-lg focus:ring-red-500 dark:bg-gray-700 focus:border-red-500 block w-full p-2.5 dark:text-red-500 dark:placeholder-red-500 dark:border-red-500' : 'bg-gray-50 border border-gray-300 text-gray-900 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white',
                   " block w-full p-2.5"
                 )}
                 placeholder="username"/>
          {state?.errors?.username && (<p className="mt-2 text-sm text-red-600 dark:text-red-500">{state.errors.username}</p>)}
        </div>
        <div>
          <label htmlFor="password"
                 className={classNames(
                   state?.errors?.username ? 'text-red-600 dark:text-red-500' : 'text-gray-900 dark:text-white',
                   "block mb-2 text-sm font-medium"
                 )}>Password</label>
          <input type="password" name="password" placeholder="••••••••"
                 className={classNames(
                   state?.errors?.password ?
                     'bg-red-50 border border-red-500 text-red-900 placeholder-red-700 text-sm rounded-lg focus:ring-red-500 dark:bg-gray-700 focus:border-red-500 block w-full p-2.5 dark:text-red-500 dark:placeholder-red-500 dark:border-red-500' : 'bg-gray-50 border border-gray-300 text-gray-900 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white',
                   " block w-full p-2.5"
                 )}

          />
          {state?.errors?.password && (<p className="mt-2 text-sm text-red-600 dark:text-red-500">{state.errors.password}</p>)}
        </div>
        <div className="flex items-center justify-between">
          <div className="flex items-start">
            <div className="flex items-center h-5">
              <input name="remember" aria-describedby="remember" type="checkbox"
                     className="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-primary-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-600 dark:ring-offset-gray-800"
                     required=""/>
            </div>
            <div className="ml-3 text-sm">
              <label htmlFor="remember" className="text-gray-500 dark:text-gray-300">Remember me</label>
            </div>
          </div>
          <a href="#" className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-500">Forgot
            password?</a>
        </div>
        <button type="submit"
                className="w-full text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Sign
          in
        </button>
        <p className="text-sm font-light text-gray-500 dark:text-gray-400">
          Don’t have an account yet? <Link href="/register" className="font-medium text-primary-600 hover:underline dark:text-primary-500">Sign up</Link>
        </p>
      </form>
    </div>
  );
};

export default Login