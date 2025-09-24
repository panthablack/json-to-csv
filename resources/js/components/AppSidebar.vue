<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue'
import NavMain from '@/components/NavMain.vue'
import NavUser from '@/components/NavUser.vue'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { dashboard } from '@/routes'
import { type NavItem } from '@/types'
import { Link } from '@inertiajs/vue3'
import { Download, LayoutGrid, Settings, Upload } from 'lucide-vue-next'
import AppLogo from './AppLogo.vue'

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard().url,
    icon: LayoutGrid,
  },
  {
    title: 'Upload JSON',
    href: '/json-upload',
    icon: Upload,
  },
  {
    title: 'Configure CSV',
    href: '/csv-config',
    icon: Settings,
  },
  {
    title: 'Export & Download',
    href: '/export',
    icon: Download,
  },
]

const footerNavItems: NavItem[] = []
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="dashboard().url">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
