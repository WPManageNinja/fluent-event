/**
 * A known bug's test asserts the correct behaviour through this: it passes while the bug is
 * there and fails once the bug is fixed, so the fix has to update the test too. Only the
 * assertion about the bug goes inside; setup is asserted outside, so a broken setup can't
 * pass for a bug that is still open.
 */
export async function expectKnownIssue(issue, assertion) {
    try {
        await assertion();
    } catch (error) {
        return;
    }

    throw new Error(`${issue} is fixed: assert this directly and drop expectKnownIssue().`);
}
