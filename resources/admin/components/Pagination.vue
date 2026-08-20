<template>
    <div v-if="!hidden" class="fe-pagination">
        <el-select
            class="fe-pagination__size"
            :model-value="per_page"
            @change="changeSize"
        >
            <el-option
                v-for="size in page_sizes"
                :key="size"
                :value="size"
                :label="$t('%d / page', size)"
            />
        </el-select>

        <el-pagination
            :background="true"
            layout="total, prev, pager, next"
            :current-page="current_page"
            :page-size="per_page"
            :total="total"
            @current-change="changePage"
        />
    </div>
</template>

<script type="text/babel">
export default {
    name: 'Pagination',
    props: {
        pagination: {
            required: true,
            type: Object
        },
        extra_sizes: {
            required: false,
            type: Array,
            default() {
                return [];
            }
        },
        hide_on_single: {
            required: false,
            type: Boolean,
            default() {
                return false;
            }
        }
    },
    computed: {
        current_page: {
            get() {
                return +(
                    this.pagination.page ??
                    this.pagination.current_page ??
                    1
                );
            },
            set(value) {
                this.pagination.current_page = value;

                if ('page' in this.pagination) {
                    this.pagination.page = value;
                }
            }
        },
        per_page: {
            get() {
                return +(
                    this.pagination.limit ??
                    this.pagination.per_page ??
                    10
                );
            },
            set(value) {
                this.pagination.per_page = value;

                if ('limit' in this.pagination) {
                    this.pagination.limit = value;
                }
            }
        },
        total() {
            return +this.pagination.total;
        },
        page_sizes() {
            const sizes = [10, 20, 50, 80, 100, ...(this.extra_sizes || [])];

            if (!sizes.includes(this.per_page)) {
                sizes.push(this.per_page);
            }

            return sizes.sort((a, b) => a - b);
        },
        hidden() {
            return this.hide_on_single && this.total <= this.per_page;
        }
    },
    methods: {
        /**
         * Jump to a specific page number and emit `fetch` so the parent
         * reloads data for the new page.
         * @param {number} page
         */
        changePage(page) {
            this.current_page = page;
            this.$emit('fetch');
        },
        /**
         * Change the page size, reset to page 1, emit `per_page_change`
         * for anyone tracking the preference, then emit `fetch`.
         * @param {number} size
         */
        changeSize(size) {
            this.per_page = size;

            this.current_page = 1;

            this.$emit('per_page_change', size);

            this.$emit('fetch');
        }
    }
}
</script>
