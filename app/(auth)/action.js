'use server'
import {LoginFormSchema} from "@/app/(auth)/login/definition";
import {api} from "@/app/actions/api";

export async function login(state, formData){

  // Validate form fields
  const validatedFields = LoginFormSchema.safeParse({
    username: formData.get('username'),
    password: formData.get('password'),
  })

  // If any form fields are invalid, return early
  if (!validatedFields.success) {
    return {
      success: false,
      errors: validatedFields.error.flatten().fieldErrors,
    }
  }
  console.log(validatedFields);
  try {
    api.login({...validatedFields.data})

  } catch (error) {
    console.log(error)
    return {
      success: false,
      message: error,
    }
  }




}