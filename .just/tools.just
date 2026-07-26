# Rust developer tooling under bin/tools/: the guards wired into the commit hooks, and the
# operator CLIs run by hand. Binaries are built from source, never committed.

# Compile every tool in bin/tools/ and install the binaries into bin/tools/bin/
[group('tools')]
buildTools:
    cd bin/tools && cargo build --release --workspace
    mkdir -p bin/tools/bin
    @for crate in bin/tools/*/src/main.rs; do name=$(basename $(dirname $(dirname $crate))); cp "bin/tools/target/release/$name" "bin/tools/bin/$name"; echo "installed: bin/tools/bin/$name"; done

# Run the tools' own test suite
[group('tools')]
testTools:
    cd bin/tools && cargo test --release --workspace

# Scan the working tree for credentials and forbidden vocabulary
[group('tools')]
checkLeaks:
    bin/tools/bin/leak-guard
