/**
 * Comments types
 * Types for the commenting system
 */

export type CommentItem = {
  id: number;
  body: string;
  created_at: string;
  author: {
    display_name: string;
  };
};