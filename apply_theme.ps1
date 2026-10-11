$root = "c:\xampp\htdocs\seait-elearning"
$files = Get-ChildItem -Path "$root\modules", "$root\includes" -Recurse -Filter "*.php" | Select-Object -ExpandProperty FullName

$replacements = @(
    # Violet → Orange (backgrounds)
    @('bg-violet-900','bg-white'),
    @('bg-violet-800','bg-gray-50'),
    @('bg-violet-700','bg-orange-600'),
    @('bg-violet-600','bg-orange-500'),
    @('bg-violet-500','bg-orange-500'),
    @('bg-violet-100','bg-orange-100'),
    @('bg-violet-50','bg-orange-50'),
    @('hover:bg-violet-800','hover:bg-orange-700'),
    @('hover:bg-violet-700','hover:bg-orange-700'),
    @('hover:bg-violet-600','hover:bg-orange-600'),
    @('hover:bg-violet-200','hover:bg-orange-200'),
    @('hover:bg-violet-100','hover:bg-orange-100'),
    # Violet → Orange (text)
    @('text-violet-900','text-gray-900'),
    @('text-violet-700','text-orange-700'),
    @('text-violet-600','text-orange-600'),
    @('text-violet-500','text-orange-500'),
    @('text-violet-400','text-orange-400'),
    @('text-violet-300','text-orange-300'),
    @('text-violet-200','text-orange-200'),
    @('text-violet-100','text-orange-100'),
    # Violet → Orange (borders/rings/shadows)
    @('border-violet-700','border-orange-600'),
    @('border-violet-600','border-orange-500'),
    @('border-violet-500','border-orange-400'),
    @('border-violet-200','border-orange-200'),
    @('border-violet-100','border-orange-100'),
    @('focus:ring-violet-500','focus:ring-orange-400'),
    @('focus:ring-violet-600','focus:ring-orange-500'),
    @('ring-violet-500','ring-orange-400'),
    @('shadow-violet-200','shadow-orange-200'),
    @('shadow-violet-500','shadow-orange-400'),
    # Violet → Orange (gradients)
    @('from-violet-900','from-white'),
    @('from-violet-800','from-gray-50'),
    @('from-violet-700','from-orange-600'),
    @('from-violet-600','from-orange-500'),
    @('to-violet-900','to-gray-100'),
    @('to-violet-800','to-gray-50'),
    @('to-violet-700','to-orange-600'),
    @('to-violet-600','to-orange-500'),

    # Indigo → Orange (backgrounds)
    @('bg-indigo-900','bg-white'),
    @('bg-indigo-800','bg-gray-50'),
    @('bg-indigo-700','bg-orange-600'),
    @('bg-indigo-600','bg-orange-500'),
    @('bg-indigo-500','bg-orange-500'),
    @('bg-indigo-100','bg-orange-100'),
    @('bg-indigo-50','bg-orange-50'),
    @('hover:bg-indigo-800','hover:bg-orange-700'),
    @('hover:bg-indigo-700','hover:bg-orange-700'),
    @('hover:bg-indigo-600','hover:bg-orange-600'),
    @('hover:bg-indigo-100','hover:bg-orange-100'),
    # Indigo → Orange (text)
    @('text-indigo-900','text-gray-900'),
    @('text-indigo-700','text-orange-700'),
    @('text-indigo-600','text-orange-600'),
    @('text-indigo-500','text-orange-500'),
    @('text-indigo-400','text-orange-400'),
    @('text-indigo-300','text-orange-300'),
    @('text-indigo-200','text-orange-200'),
    @('text-indigo-100','text-orange-100'),
    # Indigo → Orange (borders/rings/focus)
    @('border-indigo-600','border-orange-500'),
    @('border-indigo-500','border-orange-400'),
    @('border-indigo-200','border-orange-200'),
    @('border-indigo-100','border-orange-100'),
    @('focus:ring-indigo-500','focus:ring-orange-400'),
    @('focus:ring-indigo-600','focus:ring-orange-500'),
    # Indigo → Orange (gradients)
    @('from-indigo-900','from-white'),
    @('from-indigo-800','from-gray-50'),
    @('from-indigo-700','from-orange-600'),
    @('from-indigo-600','from-orange-500'),
    @('to-indigo-900','to-gray-100'),
    @('to-indigo-800','to-gray-50'),
    @('to-indigo-700','to-orange-600'),
    @('to-indigo-600','to-orange-500'),

    # Chart.js hex colors
    @("'#8b5cf6'","'#e67e22'"),
    @("'#6366f1'","'#e67e22'"),
    @("'#4f46e5'","'#e67e22'"),
    @('"#8b5cf6"','"#e67e22"'),
    @('"#6366f1"','"#e67e22"'),
    @('"#4f46e5"','"#e67e22"'),
    @('rgba(139,92,246','rgba(230,126,34'),
    @('rgba(99,102,241','rgba(230,126,34'),
    @('rgba(79,70,229','rgba(230,126,34')
)

$changedCount = 0
foreach ($file in $files) {
    $content = Get-Content $file -Raw -Encoding UTF8
    $original = $content
    foreach ($pair in $replacements) {
        $content = $content.Replace($pair[0], $pair[1])
    }
    if ($content -ne $original) {
        Set-Content $file -Value $content -Encoding UTF8 -NoNewline
        $changedCount++
        Write-Host "Updated: $file"
    }
}
Write-Host ""
Write-Host "Done. $changedCount file(s) updated."
