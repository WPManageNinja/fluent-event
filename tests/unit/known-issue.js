/**
 * A known bug's test (titled "KNOWN ISSUE #NNN — …") asserts the correct behaviour through this.
 * While the bug is open the test reports as skipped, so a run never counts it as a pass; once the
 * bug is fixed it fails. Only the bug assertion goes inside; setup stays outside, so it fails normally.
 */
export async function expectKnownIssue(issue, context, assertion) {
    try {
        await assertion();
    } catch (error) {
        context.skip();
    }

    throw new Error(`${issue} is fixed: assert this directly and drop expectKnownIssue() and the KNOWN ISSUE title.`);
}
