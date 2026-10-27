export type Role = 'speaker' | 'reviewer' | 'admin';

export interface User {
    id: number;
    name: string;
    email?: string;
    role: Role;
}

export interface Resource<T> {
    data: T;
}

export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

export type ReviewTab = 'all' | 'pending' | 'done';

export interface Tag {
    id: number;
    name: string;
}

export type ProposalStatus = 'pending' | 'approved' | 'rejected';

export interface Proposal {
    id: number;
    title: string;
    description: string;
    status: ProposalStatus;
    has_file: boolean;
    created_at: string;
    speaker: User;
    tags: Tag[];
    reviews?: Review[];
    reviews_count?: number;
}

export interface Review {
    id: number;
    rating: number;
    comment: string | null;
    reviewer: User;
    updated_at: string;
}
