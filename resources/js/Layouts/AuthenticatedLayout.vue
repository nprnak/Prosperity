<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ToastHub from '@/Components/ToastHub.vue';
import { useSidebar } from '@/Composables/useSidebar';
import { Bars3Icon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';

const { toggle } = useSidebar();
</script>

<template>
    <div>
        <ToastHub />

        <div class="flex min-h-screen flex-col bg-slate-100">
            <nav class="sticky top-0 z-30 border-b border-brand bg-brand">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="toggle"
                            class="inline-flex items-center justify-center rounded-md p-2 text-white/80 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40"
                            aria-label="Toggle navigation"
                        >
                            <Bars3Icon class="h-6 w-6" />
                        </button>

                        <Link :href="route('dashboard')" class="flex items-center gap-2">
                            <ApplicationLogo class="h-8 w-auto object-contain" />
                            <span class="hidden text-sm font-semibold tracking-wide text-white sm:inline">Prosperity CMS</span>
                        </Link>
                    </div>

                    <div class="flex items-center gap-1">
                        <NotificationBell />

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-4 text-white transition duration-150 ease-in-out hover:bg-white/10 focus:outline-none"
                                >
                                    {{ $page.props.auth.user.name }}
                                    <ChevronDownIcon class="h-4 w-4" />
                                </button>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </nav>

            <header class="border-b border-slate-200 bg-white shadow-sm" v-if="$slots.header">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
