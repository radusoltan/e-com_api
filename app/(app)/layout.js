import {AppTopNav} from "@/app/ui/App/AppTopNav";
import {AppHeader} from "@/app/ui/App/AppHeader";



const Applayout = ({children})=>{

  return <div className="min-h-full">
      <AppTopNav />

      <AppHeader title="Header Title" />
      <main>
        <div className="mx-auto px-4 py-6 sm:px-6 lg:px-8">{children}</div>
      </main>
    </div>
}
export default Applayout