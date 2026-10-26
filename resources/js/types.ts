export type Role = 'speaker' | 'reviewer' | 'admin';

export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
}

export interface Resource<T> {
    data: T;
}
