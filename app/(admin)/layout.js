import {TopNav} from "@/app/ui/Admin/TopNav";
import {SideBarComponent} from "@/app/ui/Admin/Sidebar";
import {AdminTopNav} from "@/app/ui/Admin/AdminTopNav";

const AdminLayout = ({children})=>{

  return <>
    <div className="antialiased bg-gray-50 dark:bg-gray-900">

      <TopNav />
      {/*<AdminTopNav />*/}

      {/* Sidebar */}

      <SideBarComponent />

      <main className="p-4 md:ml-64 h-auto pt-20">
        {children}
      </main>
    </div>

  </>
}
export default AdminLayout