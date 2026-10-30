<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';
import { useTaskComments } from '@/composables/useTaskComments';
import { getIcon } from '@/components/sidebar/icons';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Modal from '@/components/ui/Modal.vue';
import type { Task, TaskComment } from '@/types';

const props = defineProps<{
    task: Task;
}>();

const { success, error: toastError } = useToast();

const page = usePage();

const {
    comments,
    commentsMeta,
    commentsLoading,
    commentsError,
    fetchComments,
    createComment,
    updateComment,
    deleteComment,
    hasMoreComments,
    nextPage,
} = useTaskComments(props.task.id);

const newCommentContent = ref('');
const submittingComment = ref(false);
const editingCommentId = ref<number | null>(null);
const editingContent = ref('');
const savingEdit = ref(false);
const deleteConfirmModal = ref(false);
const commentToDelete = ref<TaskComment | null>(null);
const deletingComment = ref(false);

function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins} menit lalu`;
    if (diffHours < 24) return `${diffHours} jam lalu`;
    if (diffDays < 7) return `${diffDays} hari lalu`;

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatFullDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function startEdit(comment: TaskComment): void {
    editingCommentId.value = comment.id;
    editingContent.value = comment.content;
}

async function saveEdit(): Promise<void> {
    if (!editingCommentId.value || !editingContent.value.trim()) return;

    savingEdit.value = true;

    const result = await updateComment(
        editingCommentId.value,
        editingContent.value.trim(),
    );

    if (result) {
        success('Komentar diperbarui', 'Komentar telah diperbarui.');
        editingCommentId.value = null;
        editingContent.value = '';
    }

    savingEdit.value = false;
}

function cancelEdit(): void {
    editingCommentId.value = null;
    editingContent.value = '';
}

function confirmDelete(comment: TaskComment): void {
    commentToDelete.value = comment;
    deleteConfirmModal.value = true;
}

async function executeDelete(): Promise<void> {
    if (!commentToDelete.value) return;

    deletingComment.value = true;

    const result = await deleteComment(commentToDelete.value.id);

    if (result) {
        success('Komentar dihapus', 'Komentar telah dihapus.');
        deleteConfirmModal.value = false;
        commentToDelete.value = null;
    }

    deletingComment.value = false;
}

async function handleSubmit(): Promise<void> {
    if (!newCommentContent.value.trim()) return;

    submittingComment.value = true;

    const result = await createComment(newCommentContent.value.trim());

    if (result) {
        newCommentContent.value = '';
    }

    submittingComment.value = false;
}

async function loadMoreComments(): Promise<void> {
    if (!hasMoreComments.value) return;

    await fetchComments(nextPage.value);
}
</script>

<template>
    <div class="space-y-6">
        <!-- Comments Section -->
        <section class="card">
            <div class="mb-4 flex items-center justify-between">
                <h3
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Komentar
                </h3>
                <span class="text-sm text-surface-500 dark:text-surface-400">
                    {{ commentsMeta.total }} komentar
                </span>
            </div>

            <!-- Comment Form -->
            <form @submit.prevent="handleSubmit" class="mb-6 space-y-3">
                <div class="flex gap-3">
                    <Avatar
                        v-if="page.props.auth.user"
                        :initials="page.props.auth.user.initials"
                        :src="page.props.auth.user.avatar"
                        size="md"
                    />
                    <div class="flex-1">
                        <Input
                            v-model="newCommentContent"
                            type="textarea"
                            rows="3"
                            placeholder="Tulis komentar..."
                            :loading="submittingComment"
                        />
                        <div class="mt-2 flex justify-end">
                            <Button
                                type="submit"
                                variant="primary"
                                size="sm"
                                :loading="submittingComment"
                                :disabled="!newCommentContent.trim()"
                            >
                                Kirim
                            </Button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Error -->
            <div
                v-if="commentsError"
                class="mb-4 rounded-lg bg-accent-50 p-3 text-sm text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
            >
                {{ commentsError }}
            </div>

            <!-- Comments List -->
            <div
                v-if="commentsLoading && comments.length === 0"
                class="space-y-3"
            >
                <div
                    v-for="i in 3"
                    :key="i"
                    class="h-20 animate-pulse rounded-lg bg-surface-100 dark:bg-surface-800"
                />
            </div>

            <div v-else-if="comments.length === 0" class="py-8 text-center">
                <p class="text-surface-500 dark:text-surface-400">
                    Belum ada komentar
                </p>
                <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">
                    Jadilah yang pertama berkomentar
                </p>
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="comment in comments"
                    :key="comment.id"
                    class="border-t border-surface-200 pt-4 first:border-0 first:pt-0 dark:border-surface-700"
                >
                    <div class="flex gap-3">
                        <Avatar
                            :initials="comment.user?.initials || '?'"
                            :src="comment.user?.avatar"
                            size="sm"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p
                                    class="truncate font-medium text-surface-900 dark:text-surface-100"
                                >
                                    {{
                                        comment.user?.name ||
                                        'Pengguna tidak dikenal'
                                    }}
                                </p>
                                <time
                                    class="text-xs whitespace-nowrap text-surface-500 dark:text-surface-400"
                                >
                                    {{ formatRelativeTime(comment.created_at) }}
                                </time>
                                <span
                                    v-if="
                                        comment.updated_at !==
                                        comment.created_at
                                    "
                                    class="text-xs text-surface-600 dark:text-surface-300"
                                >
                                    (diedit)
                                </span>
                            </div>
                            <p
                                class="mt-1 whitespace-pre-wrap text-surface-700 dark:text-surface-300"
                            >
                                {{ comment.content }}
                            </p>
                            <div
                                v-if="editingCommentId === comment.id"
                                class="mt-3 space-y-2"
                            >
                                <Input
                                    v-model="editingContent"
                                    type="textarea"
                                    rows="3"
                                    :loading="savingEdit"
                                />
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        @click="cancelEdit"
                                        :disabled="savingEdit"
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        @click="saveEdit"
                                        :loading="savingEdit"
                                    >
                                        Simpan
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-else-if="
                                    comment.can_update || comment.can_delete
                                "
                                class="mt-2 flex gap-2"
                            >
                                <Button
                                    v-if="comment.can_update"
                                    variant="ghost"
                                    size="sm"
                                    @click="startEdit(comment)"
                                >
                                    <component
                                        :is="getIcon('Edit')"
                                        class="h-3.5 w-3.5"
                                    />
                                    Edit
                                </Button>
                                <Button
                                    v-if="comment.can_delete"
                                    variant="ghost"
                                    size="sm"
                                    class="text-accent-700 dark:text-accent-300"
                                    @click="confirmDelete(comment)"
                                >
                                    <component
                                        :is="getIcon('Trash2')"
                                        class="h-3.5 w-3.5"
                                    />
                                    Hapus
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Load More -->
                <div v-if="hasMoreComments" class="mt-4 text-center">
                    <Button
                        variant="secondary"
                        @click="loadMoreComments"
                        :loading="commentsLoading"
                    >
                        Muat {{ commentsMeta.total - comments.length }} komentar
                        lainnya
                    </Button>
                </div>
            </div>
        </section>

        <!-- Delete Confirmation Modal -->
        <Modal
            :open="deleteConfirmModal"
            title="Hapus Komentar"
            @close="
                deleteConfirmModal = false;
                commentToDelete = null;
            "
        >
            <p class="text-surface-600 dark:text-surface-300">
                Apakah Anda yakin ingin menghapus komentar ini?
            </p>
            <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
                Tindakan ini tidak dapat dibatalkan.
            </p>

            <template #footer>
                <Button
                    variant="secondary"
                    @click="
                        deleteConfirmModal = false;
                        commentToDelete = null;
                    "
                >
                    Batal
                </Button>
                <Button
                    variant="destructive"
                    @click="executeDelete"
                    :loading="deletingComment"
                >
                    Hapus
                </Button>
            </template>
        </Modal>
    </div>
</template>
