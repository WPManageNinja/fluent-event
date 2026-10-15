/**
 * A known bug's test (titled "KNOWN ISSUE #NNN — …") asserts the correct behaviour through this.
 * It passes while the bug is open and fails once it is fixed, so the fix updates the test too.
 * Only the bug assertion goes inside; setup stays outside, so a broken setup can't pass.
 */
export async function expectKnownIssue(issue, assertion) {
    try {
        await assertion();
    } catch (error) {
        return;
    }

    throw new Error(`${issue} is fixed: assert this directly and drop expectKnownIssue() and the KNOWN ISSUE title.`);
}
