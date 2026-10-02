//! PHP scanning shared by the guards under `bin/tools/`.
//!
//! [`scan`] extracts comments with the symbol each one belongs to. [`code_only`] blanks
//! everything that is not code (comments, string bodies, heredocs, inline HTML) while keeping
//! every line in place, and [`outline`] finds the brace-delimited blocks on that text, so a tool
//! that counts braces or matches code patterns never trips over text inside a string.

mod code;
mod comments;

pub use code::{code_only, outline, Block, BlockKind};
pub use comments::{scan, Comment, CommentKind};
