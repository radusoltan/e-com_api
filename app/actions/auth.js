import {LoginFormSchema} from "@/app/(auth)/login/definition";
import {api} from "@/app/actions/api";

export const login = async (state, formData)=>{

  const validatedFields = LoginFormSchema.safeParse({
    username: formData.get('username'),
    password: formData.get('password'),
  })

  if(!validatedFields.success){
    return {
      success: false,
      errors: validatedFields.error.flatten().fieldErrors,
      message: "Invalid form data. Please check your inputs"
    }
  }

  try {
    const {username, password} = validatedFields.data

    const data = await api.login({username, password})

    console.log(data)

  } catch (e) {

  }

}