<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const notifications = computed(() => page.props.notifications || { unread_count: 0, recent: [] });

const markAllRead = () => {
    router.post(route('notifications.mark-read'), {}, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Dropdown align="right" width="72" content-classes="bg-white dark:bg-gray-700">
        <template #trigger>
            <button
                type="button"
                class="relative rounded-full p-2 text-gray-500 transition hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:text-gray-300"
                aria-label="Notifications"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                <span
                    v-if="notifications.unread_count"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                >
                    {{ notifications.unread_count > 9 ? '9+' : notifications.unread_count }}
                </span>
            </button>
        </template>

        <template #content>
            <div class="w-72">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Notifications</span>
                    <button
                        v-if="notifications.unread_count"
                        class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                        @click="markAllRead"
                    >
                        Mark all as read
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto">
                    <div
                        v-for="item in notifications.recent"
                        :key="item.id"
                        class="border-b border-gray-50 px-4 py-3 last:border-0 dark:border-gray-700"
                        :class="item.read ? 'opacity-60' : 'bg-indigo-50/50 dark:bg-indigo-900/10'"
                    >
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ item.title }}</p>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ item.message }}</p>
                        <p class="mt-1 text-[11px] text-gray-400">{{ item.created_at }}</p>
                    </div>

                    <p v-if="!notifications.recent.length" class="px-4 py-6 text-center text-sm text-gray-500">
                        No notifications yet.
                    </p>
                </div>
            </div>
        </template>
    </Dropdown>
</template>
