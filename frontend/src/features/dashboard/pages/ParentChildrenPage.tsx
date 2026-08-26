import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar, Badge, Card, CardContent, EmptyState, Skeleton } from '@/components/ui'
import { STUDENT_STATUS_LABEL_KEYS } from '@/types/enums'
import { routePaths } from '@/routes/routePaths'

export function ParentChildrenPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: children, isLoading } = useQuery({ queryKey: queryKeys.parentChildren, queryFn: parentPortalApi.children })

  return (
    <div>
      <PageHeader title={t('nav.my_children')} description={t('students.myChildrenDescription')} />

      {isLoading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 2 }).map((_, i) => (
            <Skeleton key={i} className="h-24 w-full" />
          ))}
        </div>
      )}

      {!isLoading && children?.length === 0 && (
        <EmptyState title={t('students.noChildrenLinkedYet')} description={t('students.contactSchoolOfficeHint')} />
      )}

      {!isLoading && (children?.length ?? 0) > 0 && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {children?.map((child) => (
            <Card
              key={child.id}
              className="cursor-pointer transition-shadow hover:shadow-md"
              onClick={() => navigate(routePaths.parentChildProfile(child.id))}
            >
              <CardContent className="flex items-center gap-4 pt-4 sm:pt-6">
                <Avatar name={child.full_name} src={child.photo_url} size={48} />
                <div>
                  <p className="font-medium">{child.full_name}</p>
                  <p className="text-sm text-muted-foreground">
                    {child.grade_level?.name} {child.section ? `- ${child.section.name}` : ''}
                  </p>
                  <Badge variant={child.status === 'active' ? 'success' : 'default'} className="mt-1">
                    {t(STUDENT_STATUS_LABEL_KEYS[child.status])}
                  </Badge>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
