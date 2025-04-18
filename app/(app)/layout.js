import {TopNav} from "@/app/ui/App/TopNav";
import {AppHeader} from "@/app/ui/App/AppHeader";



const Applayout = ({children})=>{

  return <div className="min-h-full">
      <TopNav />

      <AppHeader title="Header Title" />
      <main>
        <div className="mx-auto px-4 py-6 sm:px-6 lg:px-8">{children}</div>
      </main>
    </div>
}
export default Applayout